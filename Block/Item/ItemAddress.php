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

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The back-office screen of a catalogue item: where the block card sits, and
 * where the builder of an item block leads back to.
 *
 * The core screens are addressed by route name; the store screen of the
 * Dealer module declares no route name in every version, so its path is
 * spelled out the way the module's own listing links to it.
 */
final readonly class ItemAddress
{
    public function __construct(
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function adminUrl(ItemType $type, int $itemId): string
    {
        return match ($type) {
            ItemType::Product => $this->urls->generate('admin.products.update', ['product_id' => $itemId]),
            ItemType::Category => $this->urls->generate('admin.categories.update', ['category_id' => $itemId]),
            ItemType::Brand => $this->urls->generate('admin.brand.update', ['brand_id' => $itemId]),
            ItemType::Dealer => '/admin/module/Dealer/dealer/edit?dealer_id='.$itemId,
        };
    }
}
