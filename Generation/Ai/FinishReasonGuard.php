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

use Thelia\Domain\Ai\AiFinishReason;
use TheliaCMS\Generation\GenerationException;
use TheliaCMS\Generation\GenerationRefusedException;

/**
 * What a generator does with the reason the model stopped: a refusal and an
 * answer cut by the output limit end the generation, the other reasons let it
 * go on (the tool calls are the generator's to run, an unknown reason is not
 * a failure).
 */
final readonly class FinishReasonGuard
{
    /**
     * @param int|null $outputLimit the limit the request set, null when it took that of the usage
     * @param string   $advice      what the editor can do about a cut answer, in one sentence
     *
     * @throws GenerationRefusedException when the provider refused to answer
     * @throws GenerationException        when the output limit cut the answer
     */
    public function assertUsable(AiFinishReason $reason, ?int $outputLimit, string $advice): void
    {
        switch ($reason) {
            case AiFinishReason::ContentFilter:
                throw GenerationRefusedException::withCategory(null, null);
            case AiFinishReason::MaxTokens:
                $cutAt = null === $outputLimit ? 'the output limit' : \sprintf('%d tokens', $outputLimit);

                throw GenerationException::because(\sprintf('The answer was cut at %s: %s', $cutAt, $advice));
            case AiFinishReason::Completed:
            case AiFinishReason::ToolCalls:
            case AiFinishReason::Other:
                return;
        }
    }
}
