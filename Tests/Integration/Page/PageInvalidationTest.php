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

namespace TheliaCMS\Tests\Integration\Page;

use TheliaCMS\Http\CachePurgerInterface;
use TheliaCMS\Http\CacheTags;
use TheliaCMS\Menu\CmsMenuProvider;
use TheliaCMS\Menu\MenuCache;
use TheliaCMS\Model\CmsMenu;
use TheliaCMS\Model\CmsMenuItem;
use TheliaCMS\Model\CmsMenuItemQuery;
use TheliaCMS\Model\CmsMenuQuery;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Page\Admin\CmsPageWriter;
use TheliaCMS\Page\Admin\PageDraft;
use TheliaCMS\Partial\Definition\PageListPartial;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\TheliaCMS;

/**
 * How far a change to a page reaches in the caches of the site.
 *
 * Both ways are wrong. Too wide, and a publication empties the shared cache of
 * the whole site, shop included, because the menus are on every page. Too
 * narrow, and a page taken offline is still served, and still linked from the
 * menus, until the caches expire on their own.
 */
final class PageInvalidationTest extends CmsIntegrationTestCase
{
    /** @var CachePurgerInterface&object{purged: list<string>} */
    private CachePurgerInterface $purger;

    private CmsPageWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->purger = new class implements CachePurgerInterface {
            /** @var list<string> */
            public array $purged = [];

            public function purge(array $tags): void
            {
                array_push($this->purged, ...$tags);
            }
        };
        $this->writer = $this->writerPurgingWith($this->purger);
    }

    protected function tearDown(): void
    {
        $this->getService(MenuCache::class)->invalidate();

        parent::tearDown();
    }

    public function testPublishingAPageNoMenuLinksToDropsThatPageAlone(): void
    {
        $page = $this->createPage('Gratin de pâtes', published: false);

        $this->writer->publish($page, $this->locale());

        self::assertSame([CacheTags::partial(PageListPartial::NAME), CacheTags::page((int) $page->getId())], $this->purger->purged);
    }

    public function testPublishingAgainAPageAMenuLinksToLeavesTheMenus(): void
    {
        $page = $this->createPage('Gratin de pâtes');
        $this->linkFromTheMainMenu($page);

        $this->writer->publish($page, $this->locale());

        self::assertNotContains(CacheTags::MENUS, $this->purger->purged, 'Its title and its address are the same: the menus did not change.');
        self::assertNotContains(CacheTags::SITE, $this->purger->purged);
        self::assertContains(CacheTags::page((int) $page->getId()), $this->purger->purged);
    }

    public function testPublishingForTheFirstTimeAPageAMenuLinksToDropsTheMenus(): void
    {
        $page = $this->createPage('Gratin de pâtes', published: false);
        $this->linkFromTheMainMenu($page);

        $this->writer->publish($page, $this->locale());

        self::assertContains(CacheTags::MENUS, $this->purger->purged);
        self::assertNotContains(CacheTags::SITE, $this->purger->purged);
    }

    public function testHidingAPageTakesItOutOfItsMenuAndOutOfTheSharedCache(): void
    {
        $page = $this->createPage('Gratin de pâtes');
        $this->linkFromTheMainMenu($page);
        $menus = $this->getService(CmsMenuProvider::class);
        self::assertSame(['Gratin de pâtes'], array_column($menus->menu('main', $this->locale()), 'label'), 'The page is online: it is in the menu.');

        $this->writer->toggleVisibility($page);

        self::assertSame([], $menus->menu('main', $this->locale()), 'A hidden page is still offered by the menu.');
        self::assertContains(CacheTags::page((int) $page->getId()), $this->purger->purged, 'A hidden page is still served by the shared cache.');
        self::assertContains(CacheTags::MENUS, $this->purger->purged, 'The pages of the site still link to a hidden page.');
    }

    public function testHidingAPageNoMenuLinksToLeavesTheMenus(): void
    {
        $page = $this->createPage('Gratin de pâtes');

        $this->writer->toggleVisibility($page);

        self::assertContains(CacheTags::page((int) $page->getId()), $this->purger->purged);
        self::assertNotContains(CacheTags::MENUS, $this->purger->purged);
    }

    public function testRenamingAPageDropsThePagesWhoseBreadcrumbNamesIt(): void
    {
        $parent = $this->createPage('Recettes');
        $child = $this->createPage('Gratin de pâtes', parent: (int) $parent->getId());

        $this->writer->saveDraft($parent, $this->locale(), $this->renamed($parent, 'Nos recettes'));

        self::assertContains(CacheTags::page((int) $parent->getId()), $this->purger->purged);
        self::assertContains(CacheTags::page((int) $child->getId()), $this->purger->purged);
        self::assertNotContains(CacheTags::MENUS, $this->purger->purged);
    }

    /**
     * The address of a page is the start of the addresses of the pages under
     * it: a menu that links to one of them links to an address that is gone.
     */
    public function testANewAddressDropsTheMenusThatLinkToAPageUnderIt(): void
    {
        $parent = $this->createPage('Recettes');
        $child = $this->createPage('Gratin de pâtes', parent: (int) $parent->getId());
        $this->linkFromTheMainMenu($child);

        $this->writer->saveDraft($parent, $this->locale(), new PageDraft(title: 'Recettes', slug: 'nos-recettes'));

        self::assertContains(CacheTags::page((int) $child->getId()), $this->purger->purged);
        self::assertContains(CacheTags::MENUS, $this->purger->purged);
    }

    public function testRenamingAPageAMenuLinksToDropsTheMenus(): void
    {
        $page = $this->createPage('Recettes');
        $this->linkFromTheMainMenu($page);

        $this->writer->saveDraft($page, $this->locale(), $this->renamed($page, 'Nos recettes'));

        self::assertContains(CacheTags::MENUS, $this->purger->purged);
    }

    public function testBinningAPageAMenuLinksToTakesItOutOfTheMenu(): void
    {
        $page = $this->createPage('Gratin de pâtes');
        $this->linkFromTheMainMenu($page);
        $menus = $this->getService(CmsMenuProvider::class);
        self::assertSame(['Gratin de pâtes'], array_column($menus->menu('main', $this->locale()), 'label'));

        $this->writer->moveToTrash($page);

        self::assertSame([], $menus->menu('main', $this->locale()));
        self::assertContains(CacheTags::MENUS, $this->purger->purged);
    }

    public function testRestoringAPageDropsEveryPageOfItsBranch(): void
    {
        $parent = $this->createPage('Recettes');
        $child = $this->createPage('Gratin de pâtes', parent: (int) $parent->getId());
        $this->writer()->moveToTrash($parent);

        $this->writer->restore($parent, $this->locale());

        self::assertContains(CacheTags::page((int) $parent->getId()), $this->purger->purged);
        self::assertContains(CacheTags::page((int) $child->getId()), $this->purger->purged);
    }

    public function testChangingTheHomePageDropsTheRootOfTheSite(): void
    {
        $formerHome = $this->createPage('Accueil');
        $home = $this->createPage('Bienvenue');
        $setting = TheliaCMS::getConfigValue('home_page_id', '0');
        TheliaCMS::setConfigValue('home_page_id', (string) $formerHome->getId());

        try {
            $this->writer->setAsHome($home);
        } finally {
            TheliaCMS::setConfigValue('home_page_id', (string) $setting);
        }

        // The root of the site was filed under the former home page; the
        // address of the new one now redirects to it.
        self::assertContains(CacheTags::page((int) $formerHome->getId()), $this->purger->purged);
        self::assertContains(CacheTags::page((int) $home->getId()), $this->purger->purged);
        self::assertNotContains(CacheTags::MENUS, $this->purger->purged);
    }

    public function testMovingAPageAmongItsSiblingsDropsTheListsOfPages(): void
    {
        $parent = $this->createPage('Recettes');
        $this->createPage('Gratin de pâtes', parent: (int) $parent->getId())->setPosition(1)->save();
        $page = $this->createPage('Tarte aux pommes', parent: (int) $parent->getId());
        $page->setPosition(2)->save();

        $this->writer->move($page, -1);

        self::assertSame([CacheTags::partial(PageListPartial::NAME)], $this->purger->purged);
    }

    /**
     * A new title on the address the page has: renamed, not moved.
     */
    private function renamed(CmsPage $page, string $title): PageDraft
    {
        return new PageDraft(title: $title, slug: (string) $page->setLocale($this->locale())->getSlug());
    }

    private function linkFromTheMainMenu(CmsPage $page): void
    {
        $menu = CmsMenuQuery::create()->findOneByCode('main');
        self::assertInstanceOf(CmsMenu::class, $menu);
        CmsMenuItemQuery::create()->filterByMenuId($menu->getId())->find()->delete();
        (new CmsMenuItem())
            ->setMenuId($menu->getId())
            ->setParent(0)
            ->setPosition(1)
            ->setTargetType('page')
            ->setTargetId((int) $page->getId())
            ->save();
        $this->getService(MenuCache::class)->invalidate();
    }
}
