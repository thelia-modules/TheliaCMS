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
 * The root of the site an address belongs to, keeping its scheme, host and
 * port: a site that serves a language on a domain of its own keeps it.
 */
final class SiteRoot
{
    public static function of(string $url): string
    {
        $parts = parse_url($url);

        if (false === $parts || !isset($parts['host'])) {
            return '/';
        }

        $root = ($parts['scheme'] ?? 'https').'://'.$parts['host'];

        if (isset($parts['port'])) {
            $root .= ':'.$parts['port'];
        }

        return $root.'/';
    }
}
