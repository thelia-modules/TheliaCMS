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

use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;

/**
 * Reads an item of the catalogue of the shop, the core tables being the only
 * place it lives: the module keeps nothing of it but its document.
 */
final readonly class CatalogueItems
{
    public function find(CatalogueItemType $type, int $id, string $locale): ?CatalogueItem
    {
        if (CatalogueItemType::Dealer === $type) {
            return $this->store($id, $locale);
        }

        $model = match ($type) {
            CatalogueItemType::Product => ProductQuery::create()->findPk($id),
            CatalogueItemType::Category => CategoryQuery::create()->findPk($id),
            CatalogueItemType::Content => ContentQuery::create()->findPk($id),
            CatalogueItemType::Folder => FolderQuery::create()->findPk($id),
            CatalogueItemType::Dealer => null,
        };

        if (!$model instanceof Product && !$model instanceof Category && !$model instanceof Content && !$model instanceof Folder) {
            return null;
        }

        $model->setLocale($locale);
        $url = $model->getUrl($locale);

        return new CatalogueItem(
            $type,
            $id,
            $locale,
            trim((string) $model->getTitle()),
            self::plainOrNull($model->getChapo()),
            self::plainOrNull($model->getDescription()),
            $model instanceof Product ? (string) $model->getRef() : null,
            \is_string($url) && '' !== $url ? $url : null,
            (bool) $model->getVisible(),
        );
    }

    /**
     * A store has no address the core builds: the theme draws its page, so the
     * preview of the whole page is not offered.
     */
    private function store(int $id, string $locale): ?CatalogueItem
    {
        $store = DealerStores::find($id, $locale);

        if (null === $store) {
            return null;
        }

        $store->setLocale($locale);

        return new CatalogueItem(
            CatalogueItemType::Dealer,
            $id,
            $locale,
            trim((string) $store->getTitle()),
            self::plainOrNull($store->getDescription()),
            self::plainOrNull($store->getBigDescription()),
            null,
            null,
            (bool) $store->getVisible(),
        );
    }

    private static function plainOrNull(?string $html): ?string
    {
        $text = trim(html_entity_decode(strip_tags((string) $html), \ENT_QUOTES | \ENT_HTML5));

        return '' === $text ? null : $text;
    }
}
