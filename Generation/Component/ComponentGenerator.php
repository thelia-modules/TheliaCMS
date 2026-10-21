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

namespace TheliaCMS\Generation\Component;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Messages\Message;
use Anthropic\Messages\TextBlock;
use Psr\Log\LoggerInterface;
use TheliaCMS\Content\Site\InvalidSiteComponentException;
use TheliaCMS\Content\Site\SiteComponent;
use TheliaCMS\Content\Site\SiteComponentRepository;
use TheliaCMS\Content\Site\SiteComponentValidator;
use TheliaCMS\Generation\ClaudeClientProviderInterface;
use TheliaCMS\Generation\GenerationException;
use TheliaCMS\Generation\GenerationLog;
use TheliaCMS\Generation\GenerationObserverInterface;
use TheliaCMS\Generation\GenerationRefusedException;
use TheliaCMS\Generation\GeneratorSettings;
use TheliaCMS\Generation\InvalidGenerationException;
use TheliaCMS\Generation\Pricing;
use TheliaCMS\Generation\StreamedMessage;
use TheliaCMS\Generation\Usage;

/**
 * Asks the model for a component of the site and hands back one the site can
 * keep: checked by the validator, its code free (a new one) or unchanged (a
 * change). Nothing is saved here; the editor looks at it first.
 *
 * One conversation per request, without tools: the request, the component,
 * and the corrections it needs. A component the site refuses goes back with
 * every problem listed, twice at most. Every outcome is written to the
 * ledger, beside the calls that write pages, before it is returned or thrown.
 */
final readonly class ComponentGenerator
{
    /** How many times a refused component is sent back for correction. */
    private const int CORRECTIONS = 2;

    public function __construct(
        private ClaudeClientProviderInterface $clients,
        private GeneratorSettings $settings,
        private ComponentPrompt $prompts,
        private SiteComponentValidator $validator,
        private SiteComponentRepository $components,
        private GenerationLog $log,
        private Pricing $pricing,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws GenerationException when no component could be produced, after it was logged
     */
    public function generate(ComponentRequest $request, ?GenerationObserverInterface $observer = null): ComponentResult
    {
        $started = hrtime(true);
        $client = $this->clients->client();
        $model = $this->settings->model();
        $system = $this->prompts->system();
        $messages = [['role' => 'user', 'content' => $this->prompts->userMessage($request)]];
        $format = ['type' => 'json_schema', 'schema' => ComponentPrompt::schema()];
        $usage = new Usage();
        $turns = 0;
        $requestId = null;
        $message = null;

        $record = function (string $status, ?string $error = null, ?string $component = null) use ($request, $model, $started, &$usage, &$turns, &$requestId, &$message): int {
            return $this->log->recordComponent(
                $request->componentId ?? 0,
                $request->locale,
                $request->kind(),
                $request->text,
                $status,
                $model,
                $usage,
                $turns,
                (int) ((hrtime(true) - $started) / 1_000_000),
                $requestId,
                $message?->stopReason,
                $message?->stopDetails?->category,
                $error,
                $component,
            );
        };

        try {
            while (true) {
                ++$turns;
                $streamed = new StreamedMessage(null === $observer ? null : static fn (int $characters) => $observer->onText($characters));

                try {
                    $stream = $client->messages->createStream(
                        maxTokens: $this->settings->maxTokens(),
                        messages: $messages,
                        model: $model,
                        outputConfig: ['effort' => $this->settings->effort(), 'format' => $format],
                        system: $system,
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
                $observer?->onTurn($turns, []);

                if ('refusal' === $message->stopReason) {
                    throw GenerationRefusedException::withCategory($message->stopDetails?->category, $message->stopDetails?->explanation);
                }

                if ('max_tokens' === $message->stopReason) {
                    throw GenerationException::because(\sprintf('The answer was cut at %d tokens: raise the limit of the generator or ask for a simpler component.', $this->settings->maxTokens()));
                }

                try {
                    $component = $this->validator->validate($this->componentFrom(self::textOf($message), $request));
                } catch (InvalidSiteComponentException $exception) {
                    if ($turns > self::CORRECTIONS) {
                        throw InvalidGenerationException::withProblems($exception->problems);
                    }

                    $this->logger->info('TheliaCMS: the generated component was refused and sent back: {problems}', ['problems' => implode(' ', $exception->problems)]);
                    $observer?->onCorrection($turns, $exception->problems);
                    $messages[] = ['role' => 'assistant', 'content' => $message->content];
                    $messages[] = ['role' => 'user', 'content' => ComponentPrompt::correction($exception->problems)];

                    continue;
                }

                $durationMs = (int) ((hrtime(true) - $started) / 1_000_000);
                $logId = $record(GenerationLog::STATUS_DONE, null, ComponentPrompt::json($component));

                return new ComponentResult($component, $model, $requestId, $usage, $this->pricing->cost($model, $usage), $durationMs, $turns, $logId);
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
     * The component of the answer, still to be checked. A change keeps its
     * code; a new component takes one no component of the site holds.
     *
     * @throws InvalidSiteComponentException
     */
    private function componentFrom(string $text, ComponentRequest $request): SiteComponent
    {
        if (1 === preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $text, $match)) {
            $text = $match[1];
        }

        try {
            $answer = json_decode($text, true, 64, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw InvalidSiteComponentException::because(['The answer is not valid JSON: '.$exception->getMessage()]);
        }

        if (!\is_array($answer)) {
            throw InvalidSiteComponentException::because(['The answer is not a JSON object.']);
        }

        $problems = [];
        $props = self::decoded($answer['props'] ?? null, 'props', $problems);
        $sample = self::decoded($answer['sample'] ?? null, 'sample', $problems);
        $code = \is_string($answer['code'] ?? null) ? trim($answer['code']) : '';

        if (null !== $request->current && $code !== $request->current->code) {
            $problems[] = \sprintf('The code of the component stays "%s".', $request->current->code);
        }

        if (null === $request->current && '' !== $code && null !== $this->components->find($code)) {
            $problems[] = \sprintf('The code "%s" is already the one of a component of the site: choose another.', $code);
        }

        if (null !== $props && !array_is_list($props)) {
            $problems[] = '"props" is a JSON array.';
        }

        if ([] !== $problems) {
            throw InvalidSiteComponentException::because($problems);
        }

        /** @var list<array<string, mixed>> $props */
        return new SiteComponent(
            $code,
            \is_string($answer['label'] ?? null) ? $answer['label'] : '',
            \is_string($answer['description'] ?? null) ? $answer['description'] : '',
            $props ?? [],
            $sample ?? [],
            \is_string($answer['template'] ?? null) ? $answer['template'] : '',
            \is_string($answer['css'] ?? null) ? $answer['css'] : '',
        );
    }

    /**
     * A field the answer writes as a JSON string.
     *
     * @param list<string> $problems
     *
     * @return array<mixed>|null
     */
    private static function decoded(mixed $value, string $field, array &$problems): ?array
    {
        if (\is_array($value)) {
            return $value;
        }

        $decoded = \is_string($value) ? json_decode($value, true) : null;

        if (!\is_array($decoded)) {
            $problems[] = \sprintf('"%s" is not JSON written as a string.', $field);

            return null;
        }

        return $decoded;
    }

    private static function textOf(Message $message): string
    {
        $text = '';

        foreach ($message->content as $block) {
            if ($block instanceof TextBlock) {
                $text .= $block->text;
            }
        }

        return trim($text);
    }
}
