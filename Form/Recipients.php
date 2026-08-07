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
 * The addresses a submission is sent to.
 *
 * Written in the back office and nowhere else, and never rendered in a page:
 * a form whose recipient can be influenced from outside is a mail relay with a
 * nice interface.
 */
final readonly class Recipients
{
    public const int MAX = 10;

    /**
     * @return list<string>
     */
    public static function parse(?string $stored): array
    {
        $parts = preg_split('/[,;\s]+/', (string) $stored) ?: [];
        $addresses = [];

        foreach ($parts as $part) {
            $address = trim($part);

            if ('' === $address || \in_array($address, $addresses, true)) {
                continue;
            }

            if (false !== filter_var($address, \FILTER_VALIDATE_EMAIL)) {
                $addresses[] = $address;
            }

            if (\count($addresses) >= self::MAX) {
                break;
            }
        }

        return $addresses;
    }

    /**
     * The addresses that are not usable, so the back office can say which one
     * is wrong rather than refusing the whole line.
     *
     * @return list<string>
     */
    public static function rejected(?string $stored): array
    {
        $parts = preg_split('/[,;\s]+/', (string) $stored) ?: [];
        $rejected = [];

        foreach ($parts as $part) {
            $address = trim($part);

            if ('' !== $address && false === filter_var($address, \FILTER_VALIDATE_EMAIL)) {
                $rejected[] = $address;
            }
        }

        return $rejected;
    }

    /**
     * @param list<string> $addresses
     */
    public static function toStorage(array $addresses): string
    {
        return implode(', ', $addresses);
    }
}
