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
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use TheliaCMS\Http\CachePurgerInterface;
use TheliaCMS\Http\CacheTags;
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

        $this->linkFromTheMainMenu('content', (int) $content->getId());

        $menus = $this->getService(CmsMenuProvider::class);
        self::assertSame(['Notre histoire'], array_column($menus->menu('main', $this->locale()), 'label'), 'The content is online: it is in the menu.');

        $this->getService(EventDispatcherInterface::class)->dispatch(
            new ContentToggleVisibilityEvent($content),
            TheliaEvents::CONTENT_TOGGLE_VISIBILITY,
        );

        self::assertSame([], $menus->menu('main', $this->locale()), 'A content taken offline from its own screen is still offered by the menu.');
    }

    public function testHidingAContentAMenuLinksToDropsTheMenusFromTheSharedCache(): void
    {
        $factory = $this->createFixtureFactory();
        $content = $factory->content($factory->folder(), ['locale' => $this->locale(), 'title' => 'Notre histoire']);
        $this->linkFromTheMainMenu('content', (int) $content->getId());

        $this->listenerPurgingWith($purger = $this->recordingPurger())->onContentChange(new ContentToggleVisibilityEvent($content));

        self::assertSame([CacheTags::MENUS], $purger->purged, 'The pages of the site still link to a hidden content.');
    }

    /**
     * A catalogue import writes thousands of products: dropping the menus,
     * and with them every page of the site, for each of them empties the
     * shared cache for nothing.
     */
    public function testAProductNoMenuLinksToLeavesTheSharedCache(): void
    {
        $factory = $this->createFixtureFactory();
        $content = $factory->content($factory->folder(), ['locale' => $this->locale(), 'title' => 'Notre histoire']);
        $this->linkFromTheMainMenu('content', (int) $content->getId());
        $event = new ProductUpdateEvent(999999);

        $this->listenerPurgingWith($purger = $this->recordingPurger())->onProductChange($event);

        self::assertSame([], $purger->purged);
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

    private function linkFromTheMainMenu(string $targetType, int $targetId): void
    {
        $menu = CmsMenuQuery::findShared('main');
        self::assertInstanceOf(CmsMenu::class, $menu);
        CmsMenuItemQuery::create()->filterByMenuId($menu->getId())->find()->delete();
        (new CmsMenuItem())
            ->setMenuId($menu->getId())
            ->setParent(0)
            ->setPosition(1)
            ->setTargetType($targetType)
            ->setTargetId($targetId)
            ->save();
        $this->getService(MenuCache::class)->invalidate();
    }

    /**
     * @return CachePurgerInterface&object{purged: list<string>}
     */
    private function recordingPurger(): CachePurgerInterface
    {
        return new class implements CachePurgerInterface {
            /** @var list<string> */
            public array $purged = [];

            public function purge(array $tags): void
            {
                array_push($this->purged, ...$tags);
            }
        };
    }

    private function listenerPurgingWith(CachePurgerInterface $purger): NativeContentMenuCacheListener
    {
        return $this->purgingWith(NativeContentMenuCacheListener::class, $purger);
    }
}
