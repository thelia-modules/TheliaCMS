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

namespace TheliaCMS\Tests\Integration\Block;

use Thelia\Core\Event\Brand\BrandDeleteEvent;
use Thelia\Core\Event\Category\CategoryDeleteEvent;
use Thelia\Model\Category;
use TheliaCMS\Block\Admin\CmsBlockRepository;
use TheliaCMS\Block\Admin\CmsBlockWriter;
use TheliaCMS\Block\BlockUsageFinder;
use TheliaCMS\Block\Item\ItemBlockPurgeListener;
use TheliaCMS\Block\Item\ItemBlockRepository;
use TheliaCMS\Block\Item\ItemBlockWriter;
use TheliaCMS\Block\Item\ItemType;
use TheliaCMS\Block\Item\PublishedItemBlockRepository;
use TheliaCMS\Content\Document;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Model\CmsItemBlock;
use TheliaCMS\Model\CmsItemBlockQuery;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * The block of a catalogue item: one per item, created on first demand, kept
 * out of the reusable blocks, read by the front only once published, and gone
 * with its item.
 */
final class ItemBlockTest extends CmsIntegrationTestCase
{
    public function testAnItemGetsOneBlockCreatedOnFirstDemandAndKeepsIt(): void
    {
        $category = $this->createFixtureFactory()->category();
        $categoryId = (int) $category->getId();

        self::assertNull($this->itemBlocks()->blockFor(ItemType::Category, $categoryId));

        $block = $this->itemBlockWriter()->blockFor(ItemType::Category, $categoryId, $this->locale());
        $again = $this->itemBlockWriter()->blockFor(ItemType::Category, $categoryId, $this->locale());

        self::assertSame($block->getId(), $again->getId(), 'The second demand opens the block of the first.');
        self::assertSame('category-'.$categoryId, $block->getCode());
        self::assertSame(1, CmsItemBlockQuery::create()->filterByItemType('category')->filterByItemId($categoryId)->count());
    }

    public function testTheCodeStepsAsideWhenAReusableBlockAlreadyUsesIt(): void
    {
        $brand = $this->createFixtureFactory()->brand();
        $brandId = (int) $brand->getId();

        $reusable = new CmsBlock();
        $this->blockWriter()->save($reusable, $this->locale(), 'brand-'.$brandId, 'A block an editor named like that');

        $block = $this->itemBlockWriter()->blockFor(ItemType::Brand, $brandId, $this->locale());

        self::assertSame('brand-'.$brandId.'-2', $block->getCode());
        self::assertNotSame($reusable->getId(), $block->getId());
    }

    public function testAnItemBlockIsKeptOutOfTheReusableBlocks(): void
    {
        $category = $this->createFixtureFactory()->category();
        $block = $this->itemBlockWriter()->blockFor(ItemType::Category, (int) $category->getId(), $this->locale());

        $listed = array_map(
            static fn (array $row): int => (int) $row['block']->getId(),
            $this->blocks()->all($this->locale()),
        );

        self::assertNotContains((int) $block->getId(), $listed);
        self::assertContains((int) $block->getId(), $this->itemBlocks()->blockIds());
    }

    public function testTheFrontReadsTheBlockOnlyOncePublished(): void
    {
        $category = $this->createFixtureFactory()->category();
        $categoryId = (int) $category->getId();
        $block = $this->itemBlockWriter()->blockFor(ItemType::Category, $categoryId, $this->locale());

        $this->blockWriter()->saveContent($block, $this->locale(), Document::richText('<div class="cms-page-content"><p>Our vegetables, picked this morning.</p></div>'));

        self::assertNull($this->publishedItemBlocks()->find(ItemType::Category, $categoryId, $this->locale()), 'A draft never reaches the front.');

        $this->blockWriter()->publish($block, $this->locale());

        $published = $this->publishedItemBlocks()->find(ItemType::Category, $categoryId, $this->locale());

        self::assertNotNull($published);
        self::assertStringContainsString('picked this morning', $published->html);
        self::assertNull($this->publishedItemBlocks()->find(ItemType::Category, $categoryId, 'xx_XX'), 'Only in the language it was published in.');
    }

    public function testTheBlockGoesWithItsItem(): void
    {
        $category = $this->createFixtureFactory()->category();
        $categoryId = (int) $category->getId();
        $block = $this->itemBlockWriter()->blockFor(ItemType::Category, $categoryId, $this->locale());

        $this->deleteCategoryLikeTheCore($category);

        self::assertNull($this->itemBlocks()->blockFor(ItemType::Category, $categoryId));
        self::assertNull($this->blocks()->findLive((int) $block->getId()), 'The block itself is deleted, not only its link.');
        self::assertSame(0, CmsItemBlockQuery::create()->filterByBlockId($block->getId())->count());
    }

    public function testDeletingAnItemWithoutABlockChangesNothing(): void
    {
        $brand = $this->createFixtureFactory()->brand();
        $before = CmsItemBlockQuery::create()->count();

        $this->getService(ItemBlockPurgeListener::class)->onBrandDelete(new BrandDeleteEvent((int) $brand->getId()));

        self::assertSame($before, CmsItemBlockQuery::create()->count());
    }

    public function testADeletedBlockLeavesRoomForANewOne(): void
    {
        $category = $this->createFixtureFactory()->category();
        $categoryId = (int) $category->getId();
        $first = $this->itemBlockWriter()->blockFor(ItemType::Category, $categoryId, $this->locale());

        $this->itemBlockWriter()->deleteFor(ItemType::Category, $categoryId);
        $second = $this->itemBlockWriter()->blockFor(ItemType::Category, $categoryId, $this->locale());

        self::assertNotSame($first->getId(), $second->getId());
        self::assertSame(1, CmsItemBlockQuery::create()->filterByItemType('category')->filterByItemId($categoryId)->count());
    }

    public function testTheBlocksOfTheSubtreeGoWithTheirCategory(): void
    {
        $fixtures = $this->createFixtureFactory();
        $parent = $fixtures->category();
        $child = $fixtures->category(['parent' => (int) $parent->getId()]);
        $parentId = (int) $parent->getId();
        $childId = (int) $child->getId();

        $this->itemBlockWriter()->blockFor(ItemType::Category, $parentId, $this->locale());
        $this->itemBlockWriter()->blockFor(ItemType::Category, $childId, $this->locale());

        // The core dispatches the event for the top category only: the
        // listener reads the subtree before the core and deletes after it.
        $this->deleteCategoryLikeTheCore($parent);

        self::assertNull($this->itemBlocks()->blockFor(ItemType::Category, $parentId));
        self::assertNull($this->itemBlocks()->blockFor(ItemType::Category, $childId), 'The child category kept its block.');
    }

    public function testTheBlocksStayWhenTheCoreDidNotDeleteTheCategory(): void
    {
        $category = $this->createFixtureFactory()->category();
        $categoryId = (int) $category->getId();
        $block = $this->itemBlockWriter()->blockFor(ItemType::Category, $categoryId, $this->locale());

        // A deletion the core refused or rolled back leaves the event without
        // its category.
        $event = new CategoryDeleteEvent($categoryId);
        $listener = $this->getService(ItemBlockPurgeListener::class);
        $listener->rememberCategorySubtree($event);
        $listener->onCategoryDeleted($event);

        self::assertSame((int) $block->getId(), (int) $this->itemBlocks()->blockFor(ItemType::Category, $categoryId)?->getId());
    }

    public function testAPageReferringToTheBlockDoesNotStopItsDeletion(): void
    {
        $category = $this->createFixtureFactory()->category();
        $categoryId = (int) $category->getId();
        $block = $this->itemBlockWriter()->blockFor(ItemType::Category, $categoryId, $this->locale());

        // A placeholder written by hand: the picker never offers an item
        // block, but an id is guessable, and a reusable block in that state
        // is refused deletion.
        $this->createPage('Page pointing at an item block', html: \sprintf('<div data-cms-partial="cms-block" data-props=\'{"block":%d}\'></div>', $block->getId()));

        self::assertNotSame([], $this->getService(BlockUsageFinder::class)->pagesUsing((int) $block->getId()), 'The page does refer to the block.');

        $this->itemBlockWriter()->deleteFor(ItemType::Category, $categoryId);

        self::assertNull($this->blocks()->findLive((int) $block->getId()));
        self::assertSame(0, CmsItemBlockQuery::create()->filterByItemId($categoryId)->filterByItemType('category')->count());
    }

    public function testALinkLeftByABlockDeletedElsewhereIsReused(): void
    {
        $category = $this->createFixtureFactory()->category();
        $categoryId = (int) $category->getId();
        $first = $this->itemBlockWriter()->blockFor(ItemType::Category, $categoryId, $this->locale());

        // Soft-deleted behind the writer's back: the link row stays.
        $first->setDeletedAt(new \DateTimeImmutable())->save();
        self::assertInstanceOf(CmsItemBlock::class, $this->itemBlocks()->linkFor(ItemType::Category, $categoryId));

        $second = $this->itemBlockWriter()->blockFor(ItemType::Category, $categoryId, $this->locale());

        self::assertNotSame($first->getId(), $second->getId());
        self::assertSame((int) $second->getId(), (int) $this->itemBlocks()->linkFor(ItemType::Category, $categoryId)?->getBlockId(), 'The link now points at the new block.');
        self::assertSame(1, CmsItemBlockQuery::create()->filterByItemType('category')->filterByItemId($categoryId)->count());
    }

    /**
     * What the core does on CATEGORY_DELETE, around the two listeners: the
     * subtree is read first, the rows go, the event carries the category.
     */
    private function deleteCategoryLikeTheCore(Category $category): void
    {
        $event = new CategoryDeleteEvent((int) $category->getId());
        $listener = $this->getService(ItemBlockPurgeListener::class);

        $listener->rememberCategorySubtree($event);
        $category->delete();
        $event->setCategory($category);
        $listener->onCategoryDeleted($event);
    }

    private function itemBlockWriter(): ItemBlockWriter
    {
        return $this->getService(ItemBlockWriter::class);
    }

    private function itemBlocks(): ItemBlockRepository
    {
        return $this->getService(ItemBlockRepository::class);
    }

    private function blocks(): CmsBlockRepository
    {
        return $this->getService(CmsBlockRepository::class);
    }

    private function blockWriter(): CmsBlockWriter
    {
        return $this->getService(CmsBlockWriter::class);
    }

    private function publishedItemBlocks(): PublishedItemBlockRepository
    {
        return $this->getService(PublishedItemBlockRepository::class);
    }
}
