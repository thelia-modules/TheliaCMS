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

namespace TheliaCMS\Block\Item;

use Thelia\Model\BrandQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ProductQuery;

/**
 * The title of a catalogue item in a language, as the builder bar and the
 * block card name it, or null when the item does not exist.
 *
 * The Dealer model is reached by name: the class is absent on a site without
 * the module, and ItemTypes keeps the store out of reach there.
 */
final readonly class ItemTitle
{
    public function of(ItemType $type, int $itemId, string $locale): ?string
    {
        $item = match ($type) {
            ItemType::Product => ProductQuery::create()->joinWithI18n($locale)->findPk($itemId),
            ItemType::Category => CategoryQuery::create()->joinWithI18n($locale)->findPk($itemId),
            ItemType::Brand => BrandQuery::create()->joinWithI18n($locale)->findPk($itemId),
            ItemType::Dealer => $this->dealer($itemId, $locale),
        };

        if (null === $item) {
            return null;
        }

        $title = trim((string) $item->getTitle());

        return '' !== $title ? $title : \sprintf('#%d', $itemId);
    }

    public function exists(ItemType $type, int $itemId): bool
    {
        return match ($type) {
            ItemType::Product => null !== ProductQuery::create()->findPk($itemId),
            ItemType::Category => null !== CategoryQuery::create()->findPk($itemId),
            ItemType::Brand => null !== BrandQuery::create()->findPk($itemId),
            ItemType::Dealer => null !== $this->dealer($itemId, null),
        };
    }

    private function dealer(int $itemId, ?string $locale): ?object
    {
        $queryClass = 'Dealer\Model\DealerQuery';

        if (!class_exists($queryClass)) {
            return null;
        }

        $query = $queryClass::create();

        if (null !== $locale) {
            $query->joinWithI18n($locale);
        }

        return $query->findPk($itemId);
    }
}
