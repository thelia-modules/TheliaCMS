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

namespace TheliaCMS\Form;

/**
 * How long the submissions of a form are kept.
 *
 * Personal data is kept for as long as it is needed and not one day more, so a
 * form states its own duration and old submissions leave on their own. A site
 * where deleting is a chore somebody has to remember ends up keeping everything
 * forever, which is exactly what the rule forbids.
 */
final readonly class RetentionPolicy
{
    /** Long enough to answer a request and to follow up on it, short enough to be defensible. */
    public const int DEFAULT_DAYS = 365;

    public const int MAX_DAYS = 3650;

    /**
     * The date before which submissions of this form have to go, or null when
     * the form keeps them until someone deletes them by hand.
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
}
