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

/**
 * What groups the requests of one task in the usage log of the core: the turns
 * of a tool loop and the corrections of a refused answer share it, and the
 * next task on the same page gets another one, so that the cost of a task is
 * the sum of its own rows.
 */
final readonly class CorrelationId
{
    /** The core takes 64 characters of letters, digits and `. _ : -`. */
    private const int MAX_LENGTH = 64;

    public static function new(string|int ...$parts): string
    {
        $named = implode('.', array_map(static fn (string|int $part): string => (string) preg_replace('/[^A-Za-z0-9_:-]/', '', (string) $part), $parts));

        return substr($named, 0, self::MAX_LENGTH - 9).'.'.bin2hex(random_bytes(4));
    }
}
