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

use Propel\Runtime\Propel;
use TheliaCMS\Block\Admin\CmsBlockRepository;
use TheliaCMS\Block\Admin\CmsBlockWriter;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Model\CmsItemBlock;
use TheliaCMS\Model\Map\CmsBlockTableMap;
use TheliaCMS\Page\Admin\CmsActivityLog;

/**
 * Every write to the link between an item and its block goes through here.
 *
 * The block is written by CmsBlockWriter, the same way a reusable block is:
 * the only thing added is the row that ties it to its item, and the code and
 * title the item gives it, since nobody is asked for them.
 */
final readonly class ItemBlockWriter
{
    public function __construct(
        private ItemBlockRepository $itemBlocks,
        private CmsBlockRepository $blocks,
        private CmsBlockWriter $writer,
        private ItemTitle $titles,
        private CmsActivityLog $activityLog,
    ) {
    }

    /**
     * The block of an item, created on first demand: a block with nothing in
     * it is not something anyone wants to fill a form for before laying it
     * out. The link of a block that was deleted is reused rather than left in
     * the way of the new one.
     *
     * The block and its link are written in one transaction, and a second
     * request racing this one (a double click on "Create") ends up with the
     * block the first wrote: the unique keys refuse the duplicate, and the
     * loser reads again instead of failing.
     */
    public function blockFor(ItemType $type, int $itemId, string $locale): CmsBlock
    {
        $block = $this->itemBlocks->blockFor($type, $itemId);

        if ($block instanceof CmsBlock) {
            return $block;
        }

        $connection = Propel::getConnection(CmsBlockTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $block = new CmsBlock();
            $this->writer->save($block, $locale, $this->freeCode($type, $itemId), $this->titles->of($type, $itemId, $locale) ?? \sprintf('#%d', $itemId));

            $link = $this->itemBlocks->linkFor($type, $itemId) ?? (new CmsItemBlock())
                ->setItemType($type->value)
                ->setItemId($itemId);

            $link->setBlockId($block->getId())->save();

            $connection->commit();
        } catch (\Throwable $failure) {
            $connection->rollBack();

            $written = $this->itemBlocks->blockFor($type, $itemId);

            if ($written instanceof CmsBlock) {
                return $written;
            }

            throw $failure;
        }

        $this->activityLog->record('CREATE', (int) $block->getId(), \sprintf('CMS block #%d created for %s #%d', $block->getId(), $type->value, $itemId));

        return $block;
    }

    /**
     * Drops the block of an item, link included. Nothing is refused: an item
     * block is placed on the page of its item alone, and the item is often
     * already gone. The link cascades with the block; it is removed by hand
     * only when the block it pointed at was deleted without it.
     */
    public function deleteFor(ItemType $type, int $itemId): void
    {
        $link = $this->itemBlocks->linkFor($type, $itemId);

        if (!$link instanceof CmsItemBlock) {
            return;
        }

        $blockId = (int) $link->getBlockId();
        $block = $this->blocks->findLive($blockId);

        if ($block instanceof CmsBlock) {
            $this->writer->discard($block);
        } else {
            $link->delete();
        }

        $this->activityLog->record('DELETE', $blockId, \sprintf('CMS block #%d of %s #%d deleted', $blockId, $type->value, $itemId));
    }

    /**
     * `product-12`, or `product-12-2` when an editor happened to give a
     * reusable block that very code: the column is unique across both.
     */
    private function freeCode(ItemType $type, int $itemId): string
    {
        $base = \sprintf('%s-%d', $type->value, $itemId);
        $code = $base;
        $attempt = 1;

        while ($this->blocks->codeIsTaken($code)) {
            $code = \sprintf('%s-%d', $base, ++$attempt);
        }

        return $code;
    }
}
