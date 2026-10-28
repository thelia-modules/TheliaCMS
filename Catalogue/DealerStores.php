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

namespace TheliaCMS\Catalogue;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Model\ModuleQuery;

/**
 * The stores of the Dealer module, the one catalogue item that does not come
 * from the core. The module is optional: its classes are reached by name, and
 * a reference to one would be a fatal error on a site without it. A store is
 * found only while the module is active.
 *
 * A store has no rewritten URL of its own: another site finds it by the slug
 * of its SEO record.
 */
final class DealerStores
{
    private const string MODULE = 'Dealer';
    private const string QUERY = 'Dealer\Model\DealerQuery';
    private const string META_SEO_QUERY = 'Dealer\Model\DealerMetaSeoQuery';

    /** The module dispatches the `.before` and `.after` events of a deletion, never `dealer.dealer.delete` itself. */
    public const string DELETED_EVENT = 'dealer.dealer.delete.after';

    /**
     * The route of the edit screen of a store changed name between versions
     * of the module: `dealer_tab.view` in the attributes of 4.3,
     * `dealer.dealer_tab.view` in the routing.xml of 4.0.
     */
    private const array EDIT_ROUTES = ['dealer_tab.view', 'dealer.dealer_tab.view'];

    /**
     * @param array<string, int|string> $parameters
     */
    public static function editUrl(UrlGeneratorInterface $urls, int $id, array $parameters = []): string
    {
        foreach (self::EDIT_ROUTES as $route) {
            try {
                return $urls->generate($route, ['dealer_id' => $id] + $parameters);
            } catch (RouteNotFoundException) {
            }
        }

        throw new RouteNotFoundException('The Dealer module declares no edit screen for a store.');
    }

    public static function available(): bool
    {
        return class_exists(self::QUERY)
            && 1 === (int) ModuleQuery::create()->findOneByCode(self::MODULE)?->getActivate();
    }

    public static function find(int $id, ?string $locale = null, ?ConnectionInterface $connection = null): ?object
    {
        if (!self::available()) {
            return null;
        }

        $query = (self::QUERY)::create();

        if (null !== $locale) {
            $query->joinWithI18n($locale);
        }

        return $query->findPk($id, $connection);
    }

    public static function slugOf(int $id): ?string
    {
        if (!self::available() || !class_exists(self::META_SEO_QUERY)) {
            return null;
        }

        $slug = trim((string) (self::META_SEO_QUERY)::create()->filterByDealerId($id)->findOne()?->getSlug());

        return '' !== $slug ? $slug : null;
    }

    public static function idOfSlug(string $slug): ?int
    {
        if (!self::available() || !class_exists(self::META_SEO_QUERY)) {
            return null;
        }

        $seo = (self::META_SEO_QUERY)::create()->filterBySlug($slug)->findOne();

        return null !== $seo ? (int) $seo->getDealerId() : null;
    }
}
