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

namespace TheliaCMS\Tests\Unit\Page;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Page\TrashRetention;

/**
 * The bin deletes pages on its own, so the date it computes has to be right in
 * both directions: too early and an editor loses a page they were about to
 * restore, too late and a site keeps content it promised to remove.
 */
final class TrashRetentionTest extends TestCase
{
    private const string NOW = '2026-08-07 10:00:00';

    public function testCutsOffAtTheConfiguredNumberOfDaysAgo(): void
    {
        $cutoff = TrashRetention::cutoff(30, new \DateTimeImmutable(self::NOW));

        self::assertSame('2026-07-08 10:00:00', $cutoff?->format('Y-m-d H:i:s'));
    }

    /**
     * The cut-off crosses a month boundary and a leap day without help, which
     * is exactly what a naive subtraction of 30 × 86400 seconds gets wrong on
     * the days a clock changes.
     */
    public function testCrossesMonthAndYearBoundaries(): void
    {
        self::assertSame(
            '2026-01-02 08:30:00',
            TrashRetention::cutoff(30, new \DateTimeImmutable('2026-02-01 08:30:00'))?->format('Y-m-d H:i:s'),
        );

        self::assertSame(
            '2025-12-16 00:00:00',
            TrashRetention::cutoff(30, new \DateTimeImmutable('2026-01-15 00:00:00'))?->format('Y-m-d H:i:s'),
        );
    }

    /**
     * Zero is a deliberate "keep them until someone deletes them by hand", not
     * a request to empty the bin right now.
     */
    public function testKeepsEverythingWhenTheDurationIsZeroOrBelow(): void
    {
        self::assertNull(TrashRetention::cutoff(0, new \DateTimeImmutable(self::NOW)));
        self::assertNull(TrashRetention::cutoff(-1, new \DateTimeImmutable(self::NOW)));
    }

    public function testNeverReachesFurtherBackThanTheMaximum(): void
    {
        $cutoff = TrashRetention::cutoff(100000, new \DateTimeImmutable(self::NOW));
        $expected = (new \DateTimeImmutable(self::NOW))->modify('-'.TrashRetention::MAX_DAYS.' days');

        self::assertSame($expected->format('Y-m-d'), $cutoff?->format('Y-m-d'));
    }

    public function testFallsBackToTheDefaultWhenNothingIsConfigured(): void
    {
        self::assertSame(TrashRetention::DEFAULT_DAYS, TrashRetention::normalize(null));
        self::assertSame(TrashRetention::DEFAULT_DAYS, TrashRetention::normalize(-5));
        self::assertSame(0, TrashRetention::normalize(0));
        self::assertSame(15, TrashRetention::normalize(15));
        self::assertSame(TrashRetention::MAX_DAYS, TrashRetention::normalize(999999));
    }

    public function testCountsTheDaysLeftBeforeAPageGoes(): void
    {
        $now = new \DateTimeImmutable(self::NOW);

        self::assertSame(30, TrashRetention::daysLeft(new \DateTimeImmutable(self::NOW), 30, $now));
        self::assertSame(1, TrashRetention::daysLeft(new \DateTimeImmutable('2026-07-09 10:00:00'), 30, $now));
    }

    /**
     * A page whose deadline has passed is deleted at the next clean-up, so the
     * screen says "0 days", never a negative count.
     */
    public function testNeverCountsBelowZero(): void
    {
        self::assertSame(0, TrashRetention::daysLeft(
            new \DateTimeImmutable('2026-01-01 10:00:00'),
            30,
            new \DateTimeImmutable(self::NOW),
        ));
    }

    public function testHasNoDeadlineWhenTheBinKeepsEverything(): void
    {
        self::assertNull(TrashRetention::daysLeft(
            new \DateTimeImmutable(self::NOW),
            0,
            new \DateTimeImmutable(self::NOW),
        ));
    }
}
