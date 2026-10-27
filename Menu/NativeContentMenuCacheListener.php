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

namespace TheliaCMS\Menu;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\TheliaEvents;

/**
 * Drops the cached menus whenever a content, a folder, a category or a product
 * of the shop changes.
 *
 * A menu entry can point at any of them, and shows its title, its address and
 * whether it is online. Those are written by the screens of the shop, which
 * know nothing of this cache, so without this a renamed or hidden content kept
 * its old place in the menu until the cache expired.
 *
 * Runs after the action of the core (priority -128), so the menu is rebuilt
 * from what the write left in the database.
 */
final readonly class NativeContentMenuCacheListener
{
    private const int AFTER_THE_CORE = -128;

    public function __construct(
        private MenuCache $cache,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::CONTENT_CREATE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::CONTENT_UPDATE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::CONTENT_DELETE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::CONTENT_TOGGLE_VISIBILITY, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::CONTENT_UPDATE_POSITION, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::CONTENT_UPDATE_SEO, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::CONTENT_ADD_FOLDER, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::CONTENT_REMOVE_FOLDER, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::FOLDER_CREATE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::FOLDER_UPDATE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::FOLDER_DELETE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::FOLDER_TOGGLE_VISIBILITY, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::FOLDER_UPDATE_POSITION, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::FOLDER_UPDATE_SEO, priority: self::AFTER_THE_CORE)]
    // Only what a menu shows: the title, the address, whether it is online,
    // whether it exists. A product has many more events (prices, stock,
    // associations) which would drop the menus for nothing.
    #[AsEventListener(event: TheliaEvents::CATEGORY_CREATE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::CATEGORY_UPDATE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::CATEGORY_DELETE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::CATEGORY_TOGGLE_VISIBILITY, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::CATEGORY_UPDATE_SEO, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::PRODUCT_CREATE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::PRODUCT_UPDATE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::PRODUCT_DELETE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::PRODUCT_TOGGLE_VISIBILITY, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::PRODUCT_UPDATE_SEO, priority: self::AFTER_THE_CORE)]
    public function onNativeContentChange(): void
    {
        $this->cache->invalidate();
    }
}
