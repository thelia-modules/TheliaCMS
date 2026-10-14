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

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Brand\BrandDeleteEvent;
use Thelia\Core\Event\Category\CategoryDeleteEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CategoryQuery;

/**
 * Drops the block of an item when the item goes.
 *
 * `cms_item_block.item_id` carries no foreign key (see the schema), so this is
 * what keeps a deleted product from leaving a block behind — one that would
 * come back on the next product to be given its id.
 *
 * The core deletes a category with its whole subtree and dispatches the
 * event for the top one only, so the listener runs before the core (priority
 * above its 128) and reads the descendants while they still exist.
 *
 * The Dealer event is named by its string: the module is optional, and a
 * reference to its class would be a fatal error on a site without it. The
 * module never dispatches `dealer.dealer.delete` itself — its service
 * overrides the step that would — only the `.before` and `.after` ones.
 *
 * A row deleted straight through Propel or the API dispatches nothing and
 * keeps its block; the README says so.
 */
final readonly class ItemBlockPurgeListener
{
    private const string DEALER_DELETED = 'dealer.dealer.delete.after';

    /** Above the core action (128): the subtree has to be read before it is gone. */
    private const int BEFORE_THE_CORE = 256;

    public function __construct(
        private ItemBlockWriter $writer,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::PRODUCT_DELETE)]
    public function onProductDelete(ProductDeleteEvent $event): void
    {
        $this->writer->deleteFor(ItemType::Product, (int) $event->getProductId());
    }

    #[AsEventListener(event: TheliaEvents::CATEGORY_DELETE, priority: self::BEFORE_THE_CORE)]
    public function onCategoryDelete(CategoryDeleteEvent $event): void
    {
        foreach ($this->categoryAndDescendants((int) $event->getCategoryId()) as $categoryId) {
            $this->writer->deleteFor(ItemType::Category, $categoryId);
        }
    }

    #[AsEventListener(event: TheliaEvents::BRAND_DELETE)]
    public function onBrandDelete(BrandDeleteEvent $event): void
    {
        $this->writer->deleteFor(ItemType::Brand, (int) $event->getBrandId());
    }

    #[AsEventListener(event: self::DEALER_DELETED)]
    public function onDealerDelete(object $event): void
    {
        if (!method_exists($event, 'getDealer')) {
            return;
        }

        $dealerId = $event->getDealer()?->getId();

        if (null !== $dealerId) {
            $this->writer->deleteFor(ItemType::Dealer, (int) $dealerId);
        }
    }

    /**
     * @return list<int>
     */
    private function categoryAndDescendants(int $categoryId): array
    {
        $ids = [$categoryId];
        $parents = [$categoryId];

        while ([] !== $parents) {
            $parents = array_map(
                static fn (mixed $id): int => (int) $id,
                CategoryQuery::create()->filterByParent($parents)->select(['Id'])->find()->toArray(),
            );
            $ids = [...$ids, ...$parents];
        }

        return $ids;
    }
}
