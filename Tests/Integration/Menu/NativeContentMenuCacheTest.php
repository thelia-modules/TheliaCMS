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

namespace TheliaCMS\Tests\Integration\Menu;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Content\ContentToggleVisibilityEvent;
use Thelia\Core\Event\TheliaEvents;
use TheliaCMS\Menu\CmsMenuProvider;
use TheliaCMS\Menu\MenuCache;
use TheliaCMS\Menu\NativeContentMenuCacheListener;
use TheliaCMS\Model\CmsMenu;
use TheliaCMS\Model\CmsMenuItem;
use TheliaCMS\Model\CmsMenuItemQuery;
use TheliaCMS\Model\CmsMenuQuery;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * A menu entry pointing at a content or a folder follows what happens to it.
 *
 * Menus are cached, and the CMS screens drop the cache on every write of their
 * own; the contents and folders are written by the screens of the shop, which
 * know nothing of this cache, so their events have to drop it as well.
 */
final class NativeContentMenuCacheTest extends CmsIntegrationTestCase
{
    protected function tearDown(): void
    {
        $this->getService(MenuCache::class)->invalidate();

        parent::tearDown();
    }

    public function testHidingAContentTakesItOutOfTheMenu(): void
    {
        $factory = $this->createFixtureFactory();
        $content = $factory->content($factory->folder(), ['locale' => $this->locale(), 'title' => 'Notre histoire']);

        $menu = CmsMenuQuery::create()->findOneByCode('main');
        self::assertInstanceOf(CmsMenu::class, $menu);
        CmsMenuItemQuery::create()->filterByMenuId($menu->getId())->find()->delete();
        (new CmsMenuItem())
            ->setMenuId($menu->getId())
            ->setParent(0)
            ->setPosition(1)
            ->setTargetType('content')
            ->setTargetId((int) $content->getId())
            ->save();
        $this->getService(MenuCache::class)->invalidate();

        $menus = $this->getService(CmsMenuProvider::class);
        self::assertSame(['Notre histoire'], array_column($menus->menu('main', $this->locale()), 'label'), 'The content is online: it is in the menu.');

        $this->getService(EventDispatcherInterface::class)->dispatch(
            new ContentToggleVisibilityEvent($content),
            TheliaEvents::CONTENT_TOGGLE_VISIBILITY,
        );

        self::assertSame([], $menus->menu('main', $this->locale()), 'A content taken offline from its own screen is still offered by the menu.');
    }

    /**
     * Every event of the contents and folders, the ones added to the core later
     * included: a constant left out here is a menu that goes stale on one kind
     * of write only.
     */
    public function testEveryEventOfTheContentsAndFoldersDropsTheMenus(): void
    {
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $events = array_filter(
            (new \ReflectionClass(TheliaEvents::class))->getConstants(),
            static fn (mixed $value, string $name): bool => \is_string($value) && (str_starts_with($name, 'CONTENT_') || str_starts_with($name, 'FOLDER_')),
            \ARRAY_FILTER_USE_BOTH,
        );

        self::assertNotEmpty($events);

        foreach ($events as $name => $event) {
            $listening = array_filter(
                $dispatcher->getListeners($event),
                static fn (mixed $listener): bool => \is_array($listener) && $listener[0] instanceof NativeContentMenuCacheListener,
            );

            self::assertNotEmpty($listening, \sprintf('TheliaEvents::%s leaves the menus cached.', $name));
        }
    }
}
