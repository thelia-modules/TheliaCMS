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
use TheliaCMS\Http\CachePurger;
use TheliaCMS\Http\CacheTags;
use TheliaCMS\Menu\Admin\CmsMenuRepository;

/**
 * Drops the cached menus whenever a content, a folder, a category or a product
 * of the shop changes.
 *
 * A menu entry can point at any of them, and shows its title, its address and
 * whether it is online. Those are written by the screens of the shop, which
 * know nothing of this cache, so without this a renamed or hidden content kept
 * its old place in the menu until the cache expired.
 *
 * The menus are drawn on every page, so a shared cache in front of the site
 * keeps them as well. That one is dropped only when a menu links to what
 * changed: dropping it empties the cache of the whole site, and a catalogue
 * import writes thousands of products no menu names.
 *
 * Runs after the action of the core (priority -128), so the menu is rebuilt
 * from what the write left in the database.
 */
final readonly class NativeContentMenuCacheListener
{
    private const int AFTER_THE_CORE = -128;

    public function __construct(
        private MenuCache $cache,
        private CmsMenuRepository $menus,
        private CachePurger $httpCache,
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
    // The scheduled publication of a content or a folder. Named by their value,
    // not by TheliaEvents::CONTENT_UPDATE_PUBLICATION and FOLDER_UPDATE_PUBLICATION:
    // no released core of the 3.2 branch, which this module accepts, declares them.
    #[AsEventListener(event: 'action.updateContentPublication', priority: self::AFTER_THE_CORE)]
    public function onContentChange(object $event): void
    {
        $this->changed(MenuTargetType::Content, $event);
    }

    #[AsEventListener(event: TheliaEvents::FOLDER_CREATE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::FOLDER_UPDATE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::FOLDER_DELETE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::FOLDER_TOGGLE_VISIBILITY, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::FOLDER_UPDATE_POSITION, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::FOLDER_UPDATE_SEO, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: 'action.updateFolderPublication', priority: self::AFTER_THE_CORE)]
    public function onFolderChange(object $event): void
    {
        $this->changed(MenuTargetType::Folder, $event);
    }

    // Only what a menu shows: the title, the address, whether it is online,
    // whether it exists. A product has many more events (prices, stock,
    // associations) which would drop the menus for nothing.
    #[AsEventListener(event: TheliaEvents::CATEGORY_CREATE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::CATEGORY_UPDATE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::CATEGORY_DELETE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::CATEGORY_TOGGLE_VISIBILITY, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::CATEGORY_UPDATE_SEO, priority: self::AFTER_THE_CORE)]
    public function onCategoryChange(object $event): void
    {
        $this->changed(MenuTargetType::Category, $event);
    }

    #[AsEventListener(event: TheliaEvents::PRODUCT_CREATE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::PRODUCT_UPDATE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::PRODUCT_DELETE, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::PRODUCT_TOGGLE_VISIBILITY, priority: self::AFTER_THE_CORE)]
    #[AsEventListener(event: TheliaEvents::PRODUCT_UPDATE_SEO, priority: self::AFTER_THE_CORE)]
    public function onProductChange(object $event): void
    {
        $this->changed(MenuTargetType::Product, $event);
    }

    private function changed(MenuTargetType $type, object $event): void
    {
        $this->cache->invalidate();

        // An event that does not say which row it wrote is taken as about any
        // row of its kind.
        $id = $this->idOf($event, $type->value);

        if ($this->menus->linksTo($type, null === $id ? null : [$id])) {
            $this->httpCache->purge([CacheTags::MENUS]);
        }
    }

    /**
     * The row an event of the core wrote, read the way the shared cache of the
     * core reads it: the object it carries, else the id it names.
     */
    private function idOf(object $event, string $family): ?int
    {
        $getter = 'get'.ucfirst($family);

        if (method_exists($event, $getter)) {
            // An event built with an id alone leaves its object unset, and a
            // typed property read before it is set throws: this listener must
            // never fail the write of the shop it follows.
            try {
                $subject = $event->{$getter}();
            } catch (\Error) {
                $subject = null;
            }

            if (\is_object($subject) && method_exists($subject, 'getId') && null !== $subject->getId()) {
                return (int) $subject->getId();
            }
        }

        foreach (['get'.ucfirst($family).'Id', 'getObjectId'] as $getter) {
            if (method_exists($event, $getter) && null !== $event->{$getter}()) {
                return (int) $event->{$getter}();
            }
        }

        return null;
    }
}
