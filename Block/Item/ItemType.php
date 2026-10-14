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

/**
 * The kinds of catalogue item a block can belong to.
 *
 * Stored as the value, so the column reads the same outside the ORM. The store
 * comes from the Dealer module: it is listed here so a site running it can
 * give its stores a block, and ItemTypes says whether it is available.
 */
enum ItemType: string
{
    case Product = 'product';
    case Category = 'category';
    case Brand = 'brand';
    case Dealer = 'dealer';

    /** The module the type depends on, or null for the core. */
    public function module(): ?string
    {
        return match ($this) {
            self::Dealer => 'Dealer',
            default => null,
        };
    }

    /** What the back office calls one item of this kind; translated where shown. */
    public function label(): string
    {
        return match ($this) {
            self::Product => 'product',
            self::Category => 'category',
            self::Brand => 'brand',
            self::Dealer => 'store',
        };
    }
}
