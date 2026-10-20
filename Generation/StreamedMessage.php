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

use Anthropic\Messages\InputJSONDelta;
use Anthropic\Messages\Message;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawContentBlockStartEvent;
use Anthropic\Messages\RawContentBlockStopEvent;
use Anthropic\Messages\RawMessageDeltaEvent;
use Anthropic\Messages\RawMessageStartEvent;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\TextDelta;
use Anthropic\Messages\ToolUseBlock;

/**
 * A message read from the event stream of the API, put back together as
 * the whole message the API would have answered with in one piece: the
 * text blocks from their deltas, the tool calls from their JSON fragments,
 * the stop reason and the usage from the last events.
 *
 * Reading the stream rather than waiting for the message lets a screen show
 * the text as it is written; the generator itself only ever reads the whole.
 */
final class StreamedMessage
{
    private ?Message $message = null;

    /** @var array<int, array{type: string, id?: string, name?: string, text: string}> */
    private array $blocks = [];

    private int $characters = 0;

    /**
     * @param \Closure(int): void|null $onText told how many characters of text were written so far
     */
    public function __construct(
        private readonly ?\Closure $onText = null,
    ) {
    }

    public function absorb(object $event): void
    {
        if ($event instanceof RawMessageStartEvent) {
            $this->message = $event->message;

            return;
        }

        if ($event instanceof RawContentBlockStartEvent) {
            $block = $event->contentBlock;
            $this->blocks[$event->index] = $block instanceof ToolUseBlock
                ? ['type' => 'tool_use', 'id' => $block->id, 'name' => $block->name, 'text' => '']
                : ['type' => 'text', 'text' => $block instanceof TextBlock ? $block->text : ''];

            return;
        }

        if ($event instanceof RawContentBlockDeltaEvent) {
            $delta = $event->delta;

            if ($delta instanceof TextDelta) {
                $this->blocks[$event->index]['text'] = ($this->blocks[$event->index]['text'] ?? '').$delta->text;
                $this->characters += mb_strlen($delta->text);
                $this->onText?->__invoke($this->characters);
            } elseif ($delta instanceof InputJSONDelta) {
                $this->blocks[$event->index]['text'] = ($this->blocks[$event->index]['text'] ?? '').$delta->partialJSON;
            }

            return;
        }

        if ($event instanceof RawContentBlockStopEvent) {
            return;
        }

        if ($event instanceof RawMessageDeltaEvent && null !== $this->message) {
            $this->message['stopReason'] = $event->delta->stopReason;
            $this->message['stopSequence'] = $event->delta->stopSequence;
            $this->message['stopDetails'] = $event->delta->stopDetails;

            $usage = $this->message->usage;
            $usage['outputTokens'] = $event->usage->outputTokens;

            if (null !== $event->usage->inputTokens) {
                $usage['inputTokens'] = $event->usage->inputTokens;
            }

            if (null !== $event->usage->cacheReadInputTokens) {
                $usage['cacheReadInputTokens'] = $event->usage->cacheReadInputTokens;
            }

            if (null !== $event->usage->cacheCreationInputTokens) {
                $usage['cacheCreationInputTokens'] = $event->usage->cacheCreationInputTokens;
            }

            $this->message['usage'] = $usage;
        }
    }

    /**
     * @throws GenerationException when the stream ended before a message started
     */
    public function message(): Message
    {
        if (null === $this->message) {
            throw GenerationException::because('The Claude API closed the stream before answering.');
        }

        ksort($this->blocks);
        $content = [];

        foreach ($this->blocks as $block) {
            if ('tool_use' === $block['type']) {
                $input = '' === trim($block['text']) ? [] : json_decode($block['text'], true);
                $content[] = ToolUseBlock::with($block['id'] ?? '', \is_array($input) ? $input : [], $block['name'] ?? '');
            } elseif ('' !== $block['text']) {
                // The stream opens a text block before a tool call and may
                // leave it empty; sent back as it is, the API refuses it.
                $content[] = TextBlock::with(null, $block['text']);
            }
        }

        $this->message['content'] = $content;

        return $this->message;
    }
}
