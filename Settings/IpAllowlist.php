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

namespace TheliaCMS\Settings;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * The addresses that reach a site closed for maintenance.
 *
 * Validation and matching live together on purpose: an entry the form accepts
 * and the check at request time then ignores would lock out the very person who
 * typed it, and they would only find out with the site already closed.
 */
final readonly class IpAllowlist
{
    /**
     * A single address, v4 or v6, optionally followed by a CIDR mask.
     */
    public function isValidEntry(string $entry): bool
    {
        $entry = trim($entry);

        if ('' === $entry) {
            return false;
        }

        [$address, $mask] = array_pad(explode('/', $entry, 2), 2, null);

        if (false === filter_var($address, \FILTER_VALIDATE_IP)) {
            return false;
        }

        if (null === $mask) {
            return true;
        }

        if ('' === $mask || !ctype_digit($mask)) {
            return false;
        }

        $bits = false !== filter_var($address, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6) ? 128 : 32;

        return (int) $mask <= $bits;
    }

    /**
     * @param list<string> $entries
     *
     * @return list<string> the entries that are not an address or a range
     */
    public function rejected(array $entries): array
    {
        return array_values(array_filter($entries, fn (string $entry): bool => !$this->isValidEntry($entry)));
    }

    /**
     * @param list<string> $entries
     */
    public function contains(array $entries, ?string $ip): bool
    {
        // An unknown client address matches nothing: the site stays closed.
        if (null === $ip || '' === $ip || [] === $entries) {
            return false;
        }

        return IpUtils::checkIp($ip, $entries);
    }
}
