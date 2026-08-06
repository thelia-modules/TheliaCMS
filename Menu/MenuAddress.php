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
 * The web addresses a menu entry is allowed to point at.
 *
 * A menu is edited by the site owner, not by a developer, so the field accepts
 * what people actually type — including a bare domain name — and refuses what
 * has no business in a navigation link.
 */
final readonly class MenuAddress
{
    /** @var list<string> */
    private const array ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * @return string|null the address to put in `href`, or null when it cannot be used
     */
    public function normalize(?string $raw): ?string
    {
        $address = trim((string) $raw);

        if ('' === $address) {
            return null;
        }

        // Someone else's host, one slash short of being obvious about it.
        if (str_starts_with($address, '//')) {
            return null;
        }

        // Inside the site: a path or an anchor.
        if (str_starts_with($address, '/') || str_starts_with($address, '#')) {
            return $address;
        }

        $scheme = strtolower((string) parse_url($address, \PHP_URL_SCHEME));

        if ('' !== $scheme) {
            return \in_array($scheme, self::ALLOWED_SCHEMES, true) ? $address : null;
        }

        // No scheme and no leading slash: a domain name typed the way it is
        // read out loud. Anything without a dot is a typo, not a host.
        // Tilde delimiter: the pattern matches a `#` fragment, which would
        // close the expression early with the usual `#` delimiter.
        if (1 === preg_match('~^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+(:\d+)?([/?#].*)?$~i', $address)) {
            return 'https://'.$address;
        }

        return null;
    }
}
