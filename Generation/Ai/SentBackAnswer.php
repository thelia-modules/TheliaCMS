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

use Thelia\Domain\Ai\AiMessage;
use Thelia\Domain\Ai\AiResponse;

/**
 * An answer the generator sends back to the model with its problems. An empty
 * one is one of them, and the gateway takes no assistant message without text
 * nor tool call: it goes back as a placeholder, so that a model that answered
 * nothing is corrected like any other instead of ending the call outside the
 * ledger.
 */
final readonly class SentBackAnswer
{
    public static function of(AiResponse $response): AiMessage
    {
        return '' === trim($response->text) && !$response->hasToolCalls() ? AiMessage::assistant('(no text)') : AiMessage::fromResponse($response);
    }
}
