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

use Propel\Runtime\ActiveQuery\Criteria;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Model\CmsBlockQuery;
use TheliaCMS\Model\CmsItemBlock;
use TheliaCMS\Model\CmsItemBlockQuery;

/**
 * Which block belongs to which catalogue item.
 *
 * An item block is a `cms_block` row like any other; what sets it apart is
 * the link row, and that is the only thing read here. The block itself is
 * read through CmsBlockRepository, drafts and status included.
 */
final readonly class ItemBlockRepository
{
    /**
     * The link of an item, whether or not its block is still live.
     */
    public function linkFor(ItemType $type, int $itemId): ?CmsItemBlock
    {
        return CmsItemBlockQuery::create()
            ->filterByItemType($type->value)
            ->filterByItemId($itemId)
            ->findOne();
    }

    /**
     * The link a block carries, or null for a reusable block.
     */
    public function linkOf(CmsBlock $block): ?CmsItemBlock
    {
        if ($block->isNew()) {
            return null;
        }

        return CmsItemBlockQuery::create()->filterByBlockId($block->getId())->findOne();
    }

    /**
     * The live block of an item, or null when it has none yet — or when the
     * one it had was deleted, which reads the same from the catalogue screen.
     */
    public function blockFor(ItemType $type, int $itemId): ?CmsBlock
    {
        $link = $this->linkFor($type, $itemId);

        if (!$link instanceof CmsItemBlock) {
            return null;
        }

        return CmsBlockQuery::create()
            ->filterById($link->getBlockId())
            ->filterByDeletedAt(null, Criteria::ISNULL)
            ->findOne();
    }

    /**
     * The ids of every block that belongs to an item: what the screens and
     * the sources of reusable blocks leave out.
     *
     * @return list<int>
     */
    public function blockIds(): array
    {
        return array_map(
            static fn (mixed $id): int => (int) $id,
            CmsItemBlockQuery::create()->select(['BlockId'])->find()->toArray(),
        );
    }
}
