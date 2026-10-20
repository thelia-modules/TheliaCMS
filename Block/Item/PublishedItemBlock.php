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
 * What the front office is allowed to see of an item block: the published
 * columns, never a draft, with its dynamic blocks resolved. Data, not markup:
 * the theme decides where on the item page the block goes and what wraps it.
 */
final readonly class PublishedItemBlock
{
    public function __construct(
        public ItemType $type,
        public int $itemId,
        public string $locale,
        public string $html,
        public ?\DateTimeInterface $publishedAt = null,
    ) {
    }
}
