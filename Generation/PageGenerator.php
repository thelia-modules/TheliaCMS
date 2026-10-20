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

namespace TheliaCMS\Generation;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Messages\Message;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\ToolUseBlock;
use Psr\Log\LoggerInterface;
use TheliaCMS\Content\Document;
use TheliaCMS\Content\DocumentSchema;
use TheliaCMS\Content\DocumentValidator;
use TheliaCMS\Content\InvalidDocumentException;
use TheliaCMS\Generation\Tool\GeneratorTools;

/**
 * Asks the model for a document and hands back one the site can store.
 *
 * One conversation per request: the system prompt, the brief, then as many
 * turns as the model needs to use its tools, and the document. A document
 * the validator refuses goes back once with every problem listed; a second
 * refusal is an error. Every outcome is written to the ledger before it is
 * returned or thrown.
 */
final readonly class PageGenerator
{
    /** How many times a refused document is sent back for correction. */
    private const int CORRECTIONS = 2;

    /** A ceiling on the tool turns, so a model that keeps searching stops. */
    private const int MAX_TURNS = 12;

    public function __construct(
        private ClaudeClientProviderInterface $clients,
        private GeneratorSettings $settings,
        private PromptBuilder $prompts,
        private GeneratorTools $tools,
        private DocumentSchema $schema,
        private DocumentValidator $validator,
        private GenerationLog $log,
        private Pricing $pricing,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param GenerationObserverInterface|null $observer told of every turn, for a screen that shows the progress
     *
     * @throws GenerationException when no document could be produced, after it was logged
     */
    public function generate(GenerationRequest $request, ?GenerationObserverInterface $observer = null): GenerationResult
    {
        $started = hrtime(true);
        $client = $this->clients->client();
        $model = $this->settings->model();
        $system = $this->prompts->system();
        $prompt = $this->prompts->userMessage($request);
        $messages = [['role' => 'user', 'content' => $prompt]];
        $tools = $this->tools->definitions();
        $format = ['type' => 'json_schema', 'schema' => $this->schema->strict()];
        $usage = new Usage();
        $turns = 0;
        $corrections = 0;
        $requestId = null;

        // A closure over references: the tokens, the turns and the request id
        // are what they are when the outcome is written, not when the call
        // started.
        $record = function (string $status, ?Message $message = null, ?string $error = null, ?string $document = null) use ($request, $prompt, $model, $started, &$usage, &$turns, &$requestId): int {
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
                $document,
            );
        };

        try {
            while (true) {
                if (++$turns > self::MAX_TURNS) {
                    throw GenerationException::because(\sprintf('The model made more than %d turns without answering with a document.', self::MAX_TURNS));
                }

                // Read as a stream: the screen shows the text as it is
                // written, and a long answer no longer looks like a hang.
                $streamed = new StreamedMessage(null === $observer ? null : static fn (int $characters) => $observer->onText($characters));

                try {
                    $stream = $client->messages->createStream(
                        maxTokens: $this->settings->maxTokens(),
                        messages: $messages,
                        model: $model,
                        outputConfig: ['effort' => $this->settings->effort(), 'format' => $format],
                        system: $system,
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
                $requestId = $message->id;

                if ('refusal' === $message->stopReason) {
                    throw GenerationRefusedException::withCategory($message->stopDetails?->category, $message->stopDetails?->explanation);
                }

                if ('max_tokens' === $message->stopReason) {
                    throw GenerationException::because(\sprintf('The answer was cut at %d tokens: raise the limit of the generator or ask for a shorter page.', $this->settings->maxTokens()));
                }

                if ('tool_use' === $message->stopReason) {
                    $observer?->onTurn($turns, self::toolsCalled($message));
                    $messages[] = ['role' => 'assistant', 'content' => $message->content];
                    $messages[] = ['role' => 'user', 'content' => $this->toolResults($message, $request)];

                    continue;
                }

                $observer?->onTurn($turns, []);
                $text = $this->textOf($message);

                try {
                    $document = $this->validator->validate(self::documentFrom($text));
                    self::refuseExcluded($document, $request->target);
                } catch (InvalidDocumentException $exception) {
                    if (++$corrections > self::CORRECTIONS) {
                        throw InvalidGenerationException::withProblems($exception->problems);
                    }

                    $this->logger->info('TheliaCMS: the generated document was refused once and sent back: {problems}', ['problems' => implode(' ', $exception->problems)]);
                    $observer?->onCorrection($corrections, $exception->problems);
                    $messages[] = ['role' => 'assistant', 'content' => $message->content];
                    $messages[] = ['role' => 'user', 'content' => PromptBuilder::correction($exception->problems)];

                    continue;
                }

                $durationMs = (int) ((hrtime(true) - $started) / 1_000_000);
                $logId = $record(GenerationLog::STATUS_DONE, $message, null, $document->toJson());

                return new GenerationResult($document, $model, $requestId, $usage, $this->pricing->cost($model, $usage), $durationMs, $turns, $logId);
            }
        } catch (GenerationRefusedException $exception) {
            $exception->logId = $record(GenerationLog::STATUS_REFUSED, $message ?? null, $exception->getMessage());

            throw $exception;
        } catch (InvalidGenerationException $exception) {
            $exception->logId = $record(GenerationLog::STATUS_INVALID, $message ?? null, $exception->getMessage());

            throw $exception;
        } catch (GenerationException $exception) {
            $exception->logId = $record(GenerationLog::STATUS_FAILED, $message ?? null, $exception->getMessage());

            throw $exception;
        }
    }

    /**
     * @return list<string>
     */
    private static function toolsCalled(Message $message): array
    {
        $names = [];

        foreach ($message->content as $block) {
            if ($block instanceof ToolUseBlock) {
                $names[] = $block->name;
            }
        }

        return $names;
    }

    /**
     * A component the target rules out is refused the way the validator
     * refuses one that does not exist: sent back, with the reason.
     *
     * @throws InvalidDocumentException
     */
    private static function refuseExcluded(Document $document, GenerationTarget $target): void
    {
        $problems = [];

        foreach ($document->sections as $section) {
            if (\in_array($section->component, $target->excludedComponents, true)) {
                $problems[] = \sprintf('Section "%s" names "%s", which cannot be used in a %s.', $section->id, $section->component, $target->type);
            }
        }

        if ([] !== $problems) {
            throw InvalidDocumentException::because($problems);
        }
    }

    /**
     * @return list<array{type: 'tool_result', toolUseID: string, content: string}>
     */
    private function toolResults(Message $message, GenerationRequest $request): array
    {
        $results = [];

        foreach ($message->content as $block) {
            if ($block instanceof ToolUseBlock) {
                $results[] = [
                    'type' => 'tool_result',
                    'toolUseID' => $block->id,
                    'content' => $this->tools->run($block->name, $block->input, $request),
                ];
            }
        }

        return $results;
    }

    private function textOf(Message $message): string
    {
        $text = '';

        foreach ($message->content as $block) {
            if ($block instanceof TextBlock) {
                $text .= $block->text;
            }
        }

        return trim($text);
    }

    /**
     * @throws InvalidDocumentException
     */
    private static function documentFrom(string $text): Document
    {
        if ('' === $text) {
            throw InvalidDocumentException::because(['The answer holds no document.']);
        }

        // A model asked for JSON sometimes wraps it in a code fence anyway.
        if (1 === preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $text, $match)) {
            $text = $match[1];
        }

        try {
            $decoded = json_decode($text, true, 64, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw InvalidDocumentException::because(['The answer is not valid JSON: '.$exception->getMessage()]);
        }

        if (!\is_array($decoded)) {
            throw InvalidDocumentException::because(['The answer is not a JSON object.']);
        }

        // Held to the envelope, the model writes the props of each section as
        // a JSON string: read them back before the shape is checked.
        $problems = [];

        foreach ($decoded['sections'] ?? [] as $index => $section) {
            if (!\is_array($section) || !\is_string($section['props'] ?? null)) {
                continue;
            }

            try {
                $props = json_decode($section['props'], true, 32, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $problems[] = \sprintf('Section %d: the props are not valid JSON.', $index + 1);

                continue;
            }

            $decoded['sections'][$index]['props'] = \is_array($props) ? $props : [];
        }

        if ([] !== $problems) {
            throw InvalidDocumentException::because($problems);
        }

        return Document::fromArray($decoded);
    }
}
