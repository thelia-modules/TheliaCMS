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
 * drops that page, saving a menu drops every page (they all render it), and
 * editing a reusable block drops the pages that use it.
 */
final readonly class CacheTags
{
    public const string SITE = 'cms';
    public const string MENUS = 'cms-menu';

    public static function page(int $pageId): string
    {
        return 'cms-page-'.$pageId;
    }

    public static function block(int $blockId): string
    {
        return 'cms-block-'.$blockId;
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
