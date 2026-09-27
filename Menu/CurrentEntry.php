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

namespace TheliaCMS\Menu;

/**
 * Whether a menu entry points at the page being served.
 *
 * The path decides, and so does the query string when the entry carries one:
 * a menu listing `/search?q=recipes` and `/search?q=labels` marked both entries
 * current on any search, whatever was searched. Every parameter the entry names
 * has to hold the same value on the page; parameters it does not name (paging,
 * campaign tags) are left out, so the entry stays current on page 2 of its own
 * results. An entry pointing at another site is never current: a recruitment
 * site served on `/` passed for the home page.
 */
final class CurrentEntry
{
    /**
     * @param array<string, mixed> $currentQuery
     */
    public static function matches(mixed $url, string $currentPath, array $currentQuery, ?string $currentHost = null): bool
    {
        if (!\is_string($url)) {
            return false;
        }

        $host = parse_url($url, \PHP_URL_HOST);

        if (\is_string($host) && null !== $currentHost && 0 !== strcasecmp($host, $currentHost)) {
            return false;
        }

        $path = (string) parse_url($url, \PHP_URL_PATH);

        if ('' === $path || rtrim($path, '/') !== rtrim($currentPath, '/')) {
            return false;
        }

        parse_str((string) parse_url($url, \PHP_URL_QUERY), $entryQuery);

        foreach ($entryQuery as $name => $value) {
            if (!\array_key_exists($name, $currentQuery) || $currentQuery[$name] != $value) {
                return false;
            }
        }

        return true;
    }
}
