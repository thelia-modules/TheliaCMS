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

namespace TheliaCMS\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use TheliaCMS\Model\Base\CmsMenuQuery as BaseCmsMenuQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'cms_menu' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class CmsMenuQuery extends BaseCmsMenuQuery
{
    /** `menu_locale` of the menu every language reads unless it has its own. */
    public const string SHARED = '';

    /**
     * The shared menu of a code. A code is unique among shared menus only:
     * `findOneByCode()` may answer with the menu of one language.
     */
    public static function findShared(string $code, ?ConnectionInterface $connection = null): ?CmsMenu
    {
        return self::findForLocale($code, self::SHARED, $connection);
    }

    /**
     * The row stored for exactly this locale, '' being the shared menu.
     * Nothing falls back here: see `findServed()` for that.
     */
    public static function findForLocale(string $code, string $menuLocale, ?ConnectionInterface $connection = null): ?CmsMenu
    {
        return self::create()->filterByCode($code)->filterByMenuLocale($menuLocale)->findOne($connection);
    }

    /**
     * The menu a visitor reading `$locale` gets: the one of that language if
     * it was detached, the shared one otherwise.
     */
    public static function findServed(string $code, string $locale, ?ConnectionInterface $connection = null): ?CmsMenu
    {
        // '' sorts before any locale, so the detached row comes first.
        return self::create()
            ->filterByCode($code)
            ->filterByMenuLocale(array_values(array_unique([$locale, self::SHARED])), Criteria::IN)
            ->orderByMenuLocale(Criteria::DESC)
            ->findOne($connection);
    }

    public function filterByShared(): static
    {
        return $this->filterByMenuLocale(self::SHARED);
    }
}
