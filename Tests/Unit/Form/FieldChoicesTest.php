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

namespace TheliaCMS\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Form\FieldChoices;

/**
 * The answers of a drop-down are typed into a textarea, which means they arrive
 * with whatever line endings, blank lines and stray spaces the person's editor
 * produced.
 */
final class FieldChoicesTest extends TestCase
{
    public function testReadsOneChoicePerLine(): void
    {
        self::assertSame(['A quote', 'A question', 'Something else'], FieldChoices::parse("A quote\nA question\nSomething else"));
    }

    public function testAcceptsWindowsAndOldMacLineEndings(): void
    {
        self::assertSame(['One', 'Two', 'Three'], FieldChoices::parse("One\r\nTwo\rThree"));
    }

    public function testDropsBlankLinesAndSurroundingSpaces(): void
    {
        self::assertSame(['One', 'Two'], FieldChoices::parse("  One  \n\n\t\n Two \n  "));
    }

    /**
     * Two identical answers send back the same value, so the site cannot tell
     * which one was picked and the list reads as a mistake.
     */
    public function testKeepsOnlyTheFirstOfTwoIdenticalAnswers(): void
    {
        self::assertSame(['Yes', 'No'], FieldChoices::parse("Yes\nNo\nYes"));
    }

    public function testHasNoChoicesWhenNothingWasWritten(): void
    {
        self::assertSame([], FieldChoices::parse(null));
        self::assertSame([], FieldChoices::parse("\n \n"));
    }

    public function testStopsAtTheMaximumNumberOfChoices(): void
    {
        $written = implode("\n", array_map(static fn (int $i): string => 'Choice '.$i, range(1, 150)));

        self::assertCount(FieldChoices::MAX, FieldChoices::parse($written));
    }

    public function testCutsAChoiceThatWouldNotFitInTheColumn(): void
    {
        $choices = FieldChoices::parse(str_repeat('a', 300));

        self::assertSame(255, mb_strlen($choices[0]));
    }

    public function testRoundTripsThroughStorage(): void
    {
        $choices = ['One', 'Two'];

        self::assertSame($choices, FieldChoices::parse(FieldChoices::toText($choices)));
    }
}
