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

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Messages\Base64PDFSource;
use Anthropic\Messages\Message;
use Anthropic\Messages\ServerToolUseBlock;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\ToolUseBlock;
use Anthropic\Messages\WebFetchBlock;
use Anthropic\Messages\WebFetchToolResultBlock;
use Anthropic\Messages\WebFetchToolResultErrorBlock;
use Psr\Log\LoggerInterface;
use TheliaCMS\Content\Document;
use TheliaCMS\Content\DocumentSchema;
use TheliaCMS\Content\DocumentValidator;
use TheliaCMS\Content\InvalidDocumentException;
use TheliaCMS\Generation\Tool\GeneratorTools;
use TheliaCMS\Page\Field\FieldDefinitions;
use TheliaCMS\Page\Field\InvalidFieldsException;

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

    /** How many pages the model may read for one request, the ones the brief cites and the links it follows from them. */
    private const int WEB_FETCH_USES = 5;

    /** How much of a page read reaches the model: a long page is cut rather than filling the context. */
    private const int WEB_FETCH_CONTENT_TOKENS = 20_000;

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

        // Offered only when the brief cites a page: the request of a brief
        // that cites none stays byte for byte the one it was.
        if ([] !== $this->prompts->readableAddresses($request)) {
            $tools[] = ['type' => 'web_fetch_20260209', 'name' => 'web_fetch', 'maxUses' => self::WEB_FETCH_USES, 'maxContentTokens' => self::WEB_FETCH_CONTENT_TOKENS];
        }

        // The fields of the page travel in the envelope, as a string like the
        // props: the grammar stays the same size whatever the type defines.
        $fieldsAsked = $request->fieldsAsked();
        $format = ['type' => 'json_schema', 'schema' => $this->schema->strict($request->keptComponents(), !$fieldsAsked->isEmpty())];
        $usage = new Usage();
        $turns = 0;
        $corrections = 0;
        $requestId = null;
        // The container the code of web_fetch ran in: the next turn runs in it.
        $container = null;
        /** @var array<string, string> $addresses what each call of web_fetch asked for, by the id of the call */
        $addresses = [];
        /** @var list<array{page: WebPageRead, document: array<string, mixed>|null}> $reads */
        $reads = [];

        // A closure over references: the tokens, the turns, the request id and
        // the pages read are what they are when the outcome is written, not
        // when the call started.
        $record = function (string $status, ?Message $message = null, ?string $error = null, ?string $document = null) use ($request, $prompt, $model, $started, $client, &$usage, &$turns, &$requestId, &$reads): int {
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
                $this->counted($client, $model, $reads),
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
                        container: $container,
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
                $container = $message->container?->id ?? $container;
                self::collectReads($message, $addresses, $reads);
                $requestId = $message->id;

                if ('refusal' === $message->stopReason) {
                    throw GenerationRefusedException::withCategory($message->stopDetails?->category, $message->stopDetails?->explanation);
                }

                if ('max_tokens' === $message->stopReason) {
                    throw GenerationException::because(\sprintf('The answer was cut at %d tokens: raise the limit of the generator or ask for a shorter page.', $this->settings->maxTokens()));
                }

                // The API paused a turn of its own tools (a page being read):
                // the turn goes back as it is and the API carries on with it.
                if ('pause_turn' === $message->stopReason) {
                    $observer?->onTurn($turns, self::toolsCalled($message));
                    $messages[] = ['role' => 'assistant', 'content' => $message->content];

                    continue;
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
                    [$document, $writtenFields] = self::documentFrom($text);
                    $document = $this->validator->validate($document);
                    $fields = $fieldsAsked->isEmpty() ? null : self::fieldsFrom($writtenFields, $fieldsAsked, $request->locale, $request->target->fieldValues);
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

                // A translation changes the words, not the look: the appearance
                // of each section is the one of the source, whatever the model
                // wrote back.
                if (GenerationRequest::KIND_TRANSLATION === $request->kind && null !== $request->current) {
                    $document = $document->withAppearancesOf($request->current);
                }

                $durationMs = (int) ((hrtime(true) - $started) / 1_000_000);
                $logId = $record(GenerationLog::STATUS_DONE, $message, null, $document->toJson());

                return new GenerationResult($document, $model, $requestId, $usage, $this->pricing->cost($model, $usage), $durationMs, $turns, $logId, $fields);
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
     * The pages a turn read or failed to read. The result of a call names it
     * by id only, so the address comes from the call, which precedes it.
     *
     * @param array<string, string>                                               $addresses
     * @param list<array{page: WebPageRead, document: array<string, mixed>|null}> $reads
     */
    private static function collectReads(Message $message, array &$addresses, array &$reads): void
    {
        foreach ($message->content as $block) {
            if ($block instanceof ServerToolUseBlock && 'web_fetch' === $block->name) {
                $addresses[$block->id] = (string) ($block->input['url'] ?? '');

                continue;
            }

            if (!$block instanceof WebFetchToolResultBlock) {
                continue;
            }

            $content = $block->content;
            $url = $content instanceof WebFetchBlock ? $content->url : ($addresses[$block->toolUseID] ?? '');

            if ($content instanceof WebFetchToolResultErrorBlock) {
                $reads[] = ['page' => new WebPageRead($url, $content->errorCode), 'document' => null];

                continue;
            }

            if ($content instanceof WebFetchBlock) {
                $source = $content->content->source;
                $reads[] = [
                    'page' => new WebPageRead($url),
                    'document' => [
                        'type' => 'document',
                        'source' => $source instanceof Base64PDFSource
                            ? ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => $source->data]
                            : ['type' => 'text', 'mediaType' => 'text/plain', 'data' => $source->data],
                    ],
                ];
            }
        }
    }

    /**
     * The pages read, each with the tokens of its text, counted by the API
     * (a count is not billed). A count that fails leaves the page uncounted
     * rather than the call unrecorded.
     *
     * @param list<array{page: WebPageRead, document: array<string, mixed>|null}> $reads
     *
     * @return list<WebPageRead>
     */
    private function counted(Client $client, string $model, array $reads): array
    {
        $pages = [];

        foreach ($reads as $read) {
            if (null === $read['document']) {
                $pages[] = $read['page'];

                continue;
            }

            try {
                $tokens = $client->messages->countTokens(messages: [['role' => 'user', 'content' => [$read['document']]]], model: $model)->inputTokens;
            } catch (APIStatusException|APIConnectionException $exception) {
                $this->logger->warning('TheliaCMS: the tokens of a page read could not be counted: {message}', ['message' => $exception->getMessage()]);
                $tokens = null;
            }

            $pages[] = $read['page']->withTokens($tokens);
        }

        return $pages;
    }

    /**
     * @return list<string>
     */
    private static function toolsCalled(Message $message): array
    {
        $names = [];

        foreach ($message->content as $block) {
            if ($block instanceof ToolUseBlock || $block instanceof ServerToolUseBlock) {
                $names[] = $block->name;
            }
        }

        return $names;
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

    /**
     * The text written after the last call of a tool: a turn that read a page
     * may say so before it does, and only what follows is the document.
     */
    private function textOf(Message $message): string
    {
        $text = '';

        foreach ($message->content as $block) {
            $text = $block instanceof TextBlock ? $text.$block->text : '';
        }

        return trim($text);
    }

    /**
     * The document of the answer, and what it wrote for the fields of the
     * page, still to be read.
     *
     * @return array{0: Document, 1: mixed}
     *
     * @throws InvalidDocumentException
     */
    private static function documentFrom(string $text): array
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

        return [Document::fromArray($decoded), $decoded['fields'] ?? null];
    }

    /**
     * The values the answer wrote for the fields asked, read like a form of
     * the back office reads them; what is wrong goes back to the model with
     * the problems of the document.
     *
     * @param array<string, mixed> $held what the page holds already, which a field left out keeps
     *
     * @return array<string, int|float|string|bool|null>
     *
     * @throws InvalidDocumentException
     */
    private static function fieldsFrom(mixed $written, FieldDefinitions $asked, string $locale, array $held): array
    {
        if (\is_string($written)) {
            try {
                $written = json_decode($written, true, 8, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw InvalidDocumentException::because(['The fields are not valid JSON.']);
            }
        }

        if (!\is_array($written) || ([] !== $written && array_is_list($written))) {
            throw InvalidDocumentException::because(['The answer holds no object of fields.']);
        }

        try {
            $values = $asked->normalize($written, $locale);
        } catch (InvalidFieldsException $exception) {
            throw InvalidDocumentException::because(array_map(static fn (string $problem): string => 'Field '.$problem, $exception->problems));
        }

        $missing = $asked->missingIn([...$held, ...$values]);

        if ([] !== $missing) {
            throw InvalidDocumentException::because(array_map(static fn ($field): string => \sprintf('The field `%s` is required.', $field->code), $missing));
        }

        // normalize() keeps the codes of the fields asked only; a field the
        // answer left out is left as the page holds it.
        return $values;
    }
}
