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
use TheliaCMS\Form\RetentionPolicy;

/**
 * Submissions leave on their own after the number of days the form states.
 * Getting this wrong either deletes answers somebody still needs, or keeps
 * personal data past what the privacy policy promised.
 */
final class RetentionPolicyTest extends TestCase
{
    private const string NOW = '2026-08-07 10:00:00';

    public function testCutsOffAtTheStatedNumberOfDaysAgo(): void
    {
        $cutoff = RetentionPolicy::cutoff(30, new \DateTimeImmutable(self::NOW));

        self::assertSame('2026-07-08 10:00:00', $cutoff?->format('Y-m-d H:i:s'));
    }

    /**
     * Zero is the deliberate "keep them until someone deletes them", not a
     * request to delete everything right now.
     */
    public function testKeepsEverythingWhenNoDurationIsSet(): void
    {
        self::assertNull(RetentionPolicy::cutoff(0, new \DateTimeImmutable(self::NOW)));
        self::assertNull(RetentionPolicy::cutoff(-10, new \DateTimeImmutable(self::NOW)));
    }

    public function testNeverReachesFurtherBackThanTheMaximum(): void
    {
        $cutoff = RetentionPolicy::cutoff(100000, new \DateTimeImmutable(self::NOW));
        $expected = (new \DateTimeImmutable(self::NOW))->modify('-'.RetentionPolicy::MAX_DAYS.' days');

        self::assertSame($expected->format('Y-m-d'), $cutoff?->format('Y-m-d'));
    }

    public function testFallsBackToTheDefaultDurationWhenNoneWasChosen(): void
    {
        self::assertSame(RetentionPolicy::DEFAULT_DAYS, RetentionPolicy::normalize(null));
        self::assertSame(RetentionPolicy::DEFAULT_DAYS, RetentionPolicy::normalize(-1));
    }

    public function testKeepsZeroAsAChoiceOfItsOwn(): void
    {
        self::assertSame(0, RetentionPolicy::normalize(0));
    }

    public function testCapsADurationNobodyMeantToType(): void
    {
        self::assertSame(RetentionPolicy::MAX_DAYS, RetentionPolicy::normalize(999999));
    }
}
