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

namespace TheliaCMS\Vitrine;

/**
 * The addresses this module never answers for.
 *
 * The back office and the API write their own answers, and neither wants a page
 * of the site: no themed 404, no redirection.
 *
 * Compared as a first segment and not as a prefix. `str_starts_with($path,
 * '/admin')` also matches `/administration-des-ventes`, `/admin-des-ventes` and
 * `/administratif`, which are ordinary page addresses on a real site: they were
 * taken for the back office and served a bare 404 instead of the 404 page of the
 * site. The same shape of mistake reads harmless on every list of prefixes until
 * somebody names a page after one of them.
 */
final class ReservedPaths
{
    /** @var list<string> */
    private const array FIRST_SEGMENTS = ['admin', 'api'];

    public static function isReserved(string $path): bool
    {
        $first = explode('/', trim($path, '/'))[0];

        return \in_array($first, self::FIRST_SEGMENTS, true);
    }
}
