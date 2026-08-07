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

namespace TheliaCMS\Page;

/**
 * How long a page stays in the bin before it goes for good.
 *
 * A bin nobody empties is not a bin, it is a second copy of the site: the
 * addresses are gone but the content, the drafts and the revisions are still
 * there, and so is everything personal an editor may have written in them.
 * Forms state their own retention per form; a bin is one duration for the whole
 * site, so it is a module setting instead.
 */
final readonly class TrashRetention
{
    /** Long enough to notice a mistake and undo it, short enough to be a bin. */
    public const int DEFAULT_DAYS = 30;

    /** Ten years: past that the setting says "keep", and 0 says it plainly. */
    public const int MAX_DAYS = 3650;

    /**
     * The date before which binned pages have to go, or null when the site
     * keeps them until somebody deletes them by hand.
     */
    public static function cutoff(int $days, ?\DateTimeImmutable $now = null): ?\DateTimeImmutable
    {
        if ($days <= 0) {
            return null;
        }

        return ($now ?? new \DateTimeImmutable())->modify(\sprintf('-%d days', min($days, self::MAX_DAYS)));
    }

    public static function normalize(?int $days): int
    {
        if (null === $days || $days < 0) {
            return self::DEFAULT_DAYS;
        }

        return min($days, self::MAX_DAYS);
    }

    /**
     * Days left before a page binned at this date is deleted, or null when
     * nothing is going to delete it.
     */
    public static function daysLeft(\DateTimeInterface $deletedAt, int $days, ?\DateTimeImmutable $now = null): ?int
    {
        if ($days <= 0) {
            return null;
        }

        $now ??= new \DateTimeImmutable();
        $deadline = \DateTimeImmutable::createFromInterface($deletedAt)
            ->modify(\sprintf('+%d days', min($days, self::MAX_DAYS)));

        // A page already past its deadline is deleted on the next purge, not in
        // a negative number of days.
        return max(0, (int) ceil(($deadline->getTimestamp() - $now->getTimestamp()) / 86400));
    }
}
