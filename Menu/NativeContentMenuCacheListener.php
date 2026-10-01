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
 * Drops the cached menus whenever a content or a folder of the shop changes.
 *
 * A menu entry can point at a content or a folder, and shows its title, its
 * address and whether it is online. Those are written by the screens of the
 * shop, which know nothing of this cache, so without this a renamed or hidden
 * content kept its old place in the menu until the cache expired.
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
    public function onContentOrFolderChange(): void
    {
        $this->cache->invalidate();
    }
}
