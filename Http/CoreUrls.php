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

namespace TheliaCMS\Http;

use Thelia\Tools\URL;

/**
 * The URL service of the core, or null while nothing has built it yet: a
 * command, a test, or a component rendered before any service asked for it.
 *
 * `URL::getInstance()` promises an instance and throws a RuntimeException
 * instead of returning null when there is none, so a `null ===` check after it
 * never runs: the exception went up through a menu, a sitemap or a form link.
 */
final class CoreUrls
{
    public static function instance(): ?URL
    {
        try {
            return URL::getInstance();
        } catch (\RuntimeException) {
            return null;
        }
    }
}
