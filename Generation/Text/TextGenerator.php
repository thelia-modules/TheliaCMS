<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TheliaCMS\Generation\Text;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Messages\Message;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\ToolUseBlock;
use Psr\Log\LoggerInterface;
use TheliaCMS\Generation\ClaudeClientProviderInterface;
use TheliaCMS\Generation\GenerationException;
use TheliaCMS\Generation\GenerationLog;
use TheliaCMS\Generation\GenerationRefusedException;
use TheliaCMS\Generation\GeneratorSettings;
use TheliaCMS\Generation\InvalidGenerationException;
use TheliaCMS\Generation\Pricing;
use TheliaCMS\Generation\StreamedMessage;
use TheliaCMS\Generation\Tool\GeneratorTools;
use TheliaCMS\Generation\Usage;

/**
 * Asks the model for the text of one rich-text field and hands back
 * formatted text only. Nothing is saved here: the editor reads it and puts it
 * in the field, or not, and the form is saved as if they had typed it.
 *
 * The model may search the products, categories and pages of the site and
 * read a skill, and read the pages the brief cites, as for a page. An answer
 * that is not the envelope asked for, or holds no text once filtered, goes
 * back once. Every outcome is written to the ledger, with the kind `text`,
 * before it is returned or thrown.
 */
final readonly class TextGenerator
{
    /** The tools a text needs: what the site sells and what it publishes. */
    public const array TOOLS = ['read_skill', 'search_categories', 'search_pages', 'search_products'];

    private const int MAX_TURNS = 8;

    /** How many times an answer that cannot be used is sent back. */
    private const int CORRECTIONS = 1;

    private const int WEB_FETCH_USES = 3;

    private const int WEB_FETCH_CONTENT_TOKENS = 20000;

    public function __construct(
        private ClaudeClientProviderInterface $clients,
        private GeneratorSettings $settings,
        private TextPrompt $prompts,
        private GeneratorTools $tools,
        private TextSanitizer $sanitizer,
        private GenerationLog $log,
        private Pricing $pricing,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws GenerationException when no text could be written, after it was logged
     */
    public function generate(TextRequest $text): TextResult
    {
        $request = $text->request;
        $started = hrtime(true);
        $client = $this->clients->client();
        $model = $this->settings->textModel();
        $prompt = $this->prompts->userMessage($text);
        $messages = [['role' => 'user', 'content' => $prompt]];
        $tools = $this->tools->definitionsOf(self::TOOLS);

        // As for a page: offered only when the brief cites a page to read.
        if ($this->settings->webFetch() && [] !== $request->brief->addresses()) {
            $tools[] = ['type' => 'web_fetch_20260209', 'name' => 'web_fetch', 'maxUses' => self::WEB_FETCH_USES, 'maxContentTokens' => self::WEB_FETCH_CONTENT_TOKENS];
        }

        $format = ['type' => 'json_schema', 'schema' => TextPrompt::schema()];
        $usage = new Usage();
        $turns = 0;
        $corrections = 0;
        $requestId = null;
        $container = null;
        $message = null;

        $record = function (string $status, ?string $error = null, ?string $html = null) use ($request, $prompt, $model, $started, &$usage, &$turns, &$requestId, &$message): int {
            return $this->log->record(
                $request,
                $status,
                $prompt,
                $model,
                $usage,
                $turns,
                (int) ((hrtime(true) - $started) / 1_000_000),
                $requestId,
                $message?->stopReason,
                $message?->stopDetails?->category,
                $error,
                $html,
            );
        };

        try {
            while (true) {
                if (++$turns > self::MAX_TURNS) {
                    throw GenerationException::because(\sprintf('The model made more than %d turns without answering with a text.', self::MAX_TURNS));
                }

                $streamed = new StreamedMessage();

                try {
                    $stream = $client->messages->createStream(
                        maxTokens: $this->settings->textMaxTokens(),
                        messages: $messages,
                        container: $container,
                        model: $model,
                        outputConfig: ['effort' => $this->settings->textEffort(), 'format' => $format],
                        system: $this->prompts->system(),
                        toolChoice: ['type' => 'auto'],
                        tools: $tools,
                    );

                    foreach ($stream as $event) {
                        $streamed->absorb($event);
                    }

                    $message = $streamed->message();
                } catch (APIStatusException $exception) {
                    throw GenerationException::because(\sprintf('The Claude API answered %s: %s', $exception->type?->value ?? 'an error', $exception->getMessage()), $exception);
                } catch (APIConnectionException $exception) {
                    throw GenerationException::because('The Claude API could not be reached: '.$exception->getMessage(), $exception);
                }

                $usage = $usage->plus($message->usage);
                $container = $message->container?->id ?? $container;
                $requestId = $message->id;

                if ('refusal' === $message->stopReason) {
                    throw GenerationRefusedException::withCategory($message->stopDetails?->category, $message->stopDetails?->explanation);
                }

                if ('max_tokens' === $message->stopReason) {
                    throw GenerationException::because(\sprintf('The answer was cut at %d tokens: raise the limit of the texts in the settings or ask for a shorter text.', $this->settings->textMaxTokens()));
                }

                // A page being read by the API: the turn goes back as it is.
                if ('pause_turn' === $message->stopReason) {
                    $messages[] = ['role' => 'assistant', 'content' => $message->content];

                    continue;
                }

                if ('tool_use' === $message->stopReason) {
                    $messages[] = ['role' => 'assistant', 'content' => $message->content];
                    $messages[] = ['role' => 'user', 'content' => $this->toolResults($message, $text)];

                    continue;
                }

                $problems = [];
                $html = $this->htmlFrom(self::textOf($message), $problems);

                if ([] !== $problems) {
                    if (++$corrections > self::CORRECTIONS) {
                        throw InvalidGenerationException::withProblems($problems);
                    }

                    $this->logger->info('TheliaCMS: the generated text was refused and sent back: {problems}', ['problems' => implode(' ', $problems)]);
                    $messages[] = ['role' => 'assistant', 'content' => $message->content];
                    $messages[] = ['role' => 'user', 'content' => TextPrompt::correction($problems)];

                    continue;
                }

                $durationMs = (int) ((hrtime(true) - $started) / 1_000_000);
                $logId = $record(GenerationLog::STATUS_DONE, null, $html);

                return new TextResult($html, $model, $requestId, $usage, $this->pricing->cost($model, $usage), $durationMs, $turns, $logId);
            }
        } catch (GenerationRefusedException $exception) {
            $exception->logId = $record(GenerationLog::STATUS_REFUSED, $exception->getMessage());

            throw $exception;
        } catch (InvalidGenerationException $exception) {
            $exception->logId = $record(GenerationLog::STATUS_INVALID, $exception->getMessage());

            throw $exception;
        } catch (GenerationException $exception) {
            $exception->logId = $record(GenerationLog::STATUS_FAILED, $exception->getMessage());

            throw $exception;
        }
    }

    /**
     * The text of the envelope, filtered down to formatted text.
     *
     * @param list<string> $problems
     */
    private function htmlFrom(string $answer, array &$problems): string
    {
        if (1 === preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $answer, $match)) {
            $answer = $match[1];
        }

        $decoded = json_decode($answer, true);

        if (!\is_array($decoded) || !\is_string($decoded['html'] ?? null)) {
            $problems[] = 'The answer is not a JSON object with the text in "html".';

            return '';
        }

        $html = $this->sanitizer->text($decoded['html']);

        if ('' === trim(strip_tags($html))) {
            $problems[] = 'The text is empty: write it in "html", as paragraphs.';
        }

        return $html;
    }

    /**
     * @return list<array{type: 'tool_result', toolUseID: string, content: string}>
     */
    private function toolResults(Message $message, TextRequest $text): array
    {
        $results = [];

        foreach ($message->content as $block) {
            if ($block instanceof ToolUseBlock) {
                // Only the tools offered run, whatever name the call gives.
                $results[] = [
                    'type' => 'tool_result',
                    'toolUseID' => $block->id,
                    'content' => \in_array($block->name, self::TOOLS, true)
                        ? $this->tools->run($block->name, $block->input, $text->request)
                        : \sprintf('There is no tool called "%s".', $block->name),
                ];
            }
        }

        return $results;
    }

    /**
     * The text written after the last call of a tool.
     */
    private static function textOf(Message $message): string
    {
        $text = '';

        foreach ($message->content as $block) {
            $text = $block instanceof TextBlock ? $text.$block->text : '';
        }

        return trim($text);
    }
}
