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

/**
 * The names a cached page is filed under, so a change can drop exactly the
 * pages it affects.
 *
 * Without tags the only way to publish a correction is to empty the whole cache
 * and let the site rebuild itself under traffic. With them, publishing a page
 * drops that page, and saving a menu drops every page (they all render it).
 *
 * What a change drops is decided by `PageInvalidator`, never by purging the
 * tags a page is filed under: `cms` and `cms-menu` are on every page of the
 * site, so purging them for one page empties the whole cache.
 */
final readonly class CacheTags
{
    public const string SITE = 'cms';
    public const string MENUS = 'cms-menu';

    public static function page(int $pageId): string
    {
        return 'cms-page-'.$pageId;
    }

    /**
     * A dynamic block of a page (a list of pages, a form), read when the page
     * is served: the pages that show it change with what it renders.
     */
    public static function partial(string $partialName): string
    {
        return 'cms-partial-'.$partialName;
    }

    /**
     * What one rendered page is filed under: itself, the menus it draws, and
     * the site as a whole for the settings that appear on every page.
     *
     * @return list<string>
     */
    public static function forPage(int $pageId): array
    {
        return [self::SITE, self::MENUS, self::page($pageId)];
    }
}
