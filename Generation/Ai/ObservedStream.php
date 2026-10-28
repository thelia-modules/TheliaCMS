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

namespace TheliaCMS\Generation\Ai;

use Thelia\Domain\Ai\AiResponse;
use Thelia\Domain\Ai\Stream\StreamCompleted;
use Thelia\Domain\Ai\Stream\StreamEvent;
use Thelia\Domain\Ai\Stream\TextDelta;
use TheliaCMS\Generation\GenerationException;
use TheliaCMS\Generation\GenerationObserverInterface;

/**
 * Reads a stream of the gateway to its end: tells the observer how many
 * characters of text have arrived, and hands back the whole response the last
 * event carries.
 */
final readonly class ObservedStream
{
    /**
     * @param iterable<StreamEvent> $events
     *
     * @throws GenerationException when the stream ends without its last event
     */
    public function read(iterable $events, ?GenerationObserverInterface $observer = null): AiResponse
    {
        $characters = 0;

        foreach ($events as $event) {
            if ($event instanceof TextDelta) {
                $characters += mb_strlen($event->text);
                $observer?->onText($characters);
            } elseif ($event instanceof StreamCompleted) {
                return $event->response;
            }
            // Any other event, known or not, is nothing the observer is told of.
        }

        throw GenerationException::because('The AI gateway closed the stream before answering.');
    }
}
