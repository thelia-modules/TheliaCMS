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

/**
 * The items of the shop that carry a document of their own (docs/adr/0011).
 * The value is what `cms_item_content.item_type` stores, what the routes of
 * the back office take and what a theme passes to cms_item_content().
 */
enum CatalogueItemType: string
{
    case Product = 'product';
    case Category = 'category';
    case Content = 'content';
    case Folder = 'folder';

    /**
     * The key the shared cache of the core files the pages showing this item
     * under (`Thelia\Core\SharedCache\SharedCacheKey::forChange`): purging it
     * drops the page of the item, where the theme prints its document.
     */
    public function cacheKey(int $id): string
    {
        return $this->value.'-'.$id;
    }

    /**
     * The link from the content screen back to the edit screen of the item,
     * to translate: one sentence per type, so a language can agree it.
     */
    public function backLabel(): string
    {
        return match ($this) {
            self::Product => 'Back to the product',
            self::Category => 'Back to the category',
            self::Content => 'Back to the content',
            self::Folder => 'Back to the folder',
        };
    }
}
