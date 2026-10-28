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

namespace TheliaCMS\Tests\Unit\Generation\Ai;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\AiFinishReason;
use TheliaCMS\Generation\Ai\FinishReasonGuard;
use TheliaCMS\Generation\GenerationException;
use TheliaCMS\Generation\GenerationRefusedException;

/**
 * What the module does with the reason the model stopped.
 */
final class FinishReasonGuardTest extends TestCase
{
    public function testARefusalBecomesTheRefusalOfTheModule(): void
    {
        $this->expectException(GenerationRefusedException::class);
        $this->expectExceptionMessage('The model declined to write this page.');

        (new FinishReasonGuard())->assertUsable(AiFinishReason::ContentFilter, 2000, 'ask for less.');
    }

    public function testACutAnswerNamesTheLimitAndTheAdvice(): void
    {
        try {
            (new FinishReasonGuard())->assertUsable(AiFinishReason::MaxTokens, 2000, 'ask for a shorter text.');
            self::fail('A cut answer must end the generation.');
        } catch (GenerationException $exception) {
            self::assertNotInstanceOf(GenerationRefusedException::class, $exception);
            self::assertSame('The answer was cut at 2000 tokens: ask for a shorter text.', $exception->getMessage());
        }
    }

    public function testACutAnswerWithoutALimitOfTheRequestSaysItWasTheLimitOfTheUsage(): void
    {
        $this->expectException(GenerationException::class);
        $this->expectExceptionMessage('The answer was cut at the output limit: ask for a shorter page.');

        (new FinishReasonGuard())->assertUsable(AiFinishReason::MaxTokens, null, 'ask for a shorter page.');
    }

    #[DataProvider('reasonsThatLetTheGenerationGoOn')]
    public function testTheOtherReasonsEndNothing(AiFinishReason $reason): void
    {
        (new FinishReasonGuard())->assertUsable($reason, 2000, 'ask for less.');

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{AiFinishReason}>
     */
    public static function reasonsThatLetTheGenerationGoOn(): iterable
    {
        yield 'completed' => [AiFinishReason::Completed];
        yield 'tool calls' => [AiFinishReason::ToolCalls];
        yield 'a reason the layer does not know' => [AiFinishReason::Other];
    }
}
