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

use Thelia\Core\Content\Slot\ContentSlotLink;
use Thelia\Core\Content\Slot\ContentSlots;
use Thelia\Core\Content\Slot\ContentSlotService;
use TheliaCMS\Menu\CmsContentSlotResolver;
use TheliaCMS\Menu\MenuCache;
use TheliaCMS\Model\CmsMenu;
use TheliaCMS\Model\CmsMenuItem;
use TheliaCMS\Model\CmsMenuItemQuery;
use TheliaCMS\Model\CmsMenuQuery;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * The header and the footer of the theme, as the menus of the CMS fill them.
 *
 * The rule is the one a merchant can see: a menu holding at least one entry
 * takes the slot over, whatever becomes of its entries in the language being
 * read; an empty menu leaves the slot to the contents and folders of the shop.
 */
final class CmsContentSlotResolverTest extends CmsIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->emptyMenu('main');
        $this->emptyMenu('footer');
    }

    protected function tearDown(): void
    {
        // The rows go with the transaction, the cached menus do not.
        $this->getService(MenuCache::class)->invalidate();

        parent::tearDown();
    }

    public function testAnEmptyMenuLeavesTheSlotToTheShop(): void
    {
        $resolver = $this->getService(CmsContentSlotResolver::class);

        self::assertNull($resolver->resolve(ContentSlots::HEADER, $this->locale()));
        self::assertNull($resolver->resolve(ContentSlots::FOOTER, $this->locale()));
    }

    public function testTheMainMenuFillsTheHeaderWithItsTree(): void
    {
        $agency = $this->createPage('L agence');
        $team = $this->createPage('Equipe');

        $parent = $this->entry('main', 'page', (int) $agency->getId(), position: 1);
        $this->entry('main', 'page', (int) $team->getId(), parent: (int) $parent->getId(), position: 1);
        $this->entry('main', 'url', null, position: 2, url: 'https://blog.example', label: 'Blog', newTab: true);

        $links = $this->getService(CmsContentSlotResolver::class)->resolve(ContentSlots::HEADER, $this->locale());

        self::assertNotNull($links);
        self::assertCount(2, $links);

        self::assertSame('L agence', $links[0]->label);
        self::assertSame((string) $agency->getRewrittenUrl($this->locale()), $this->pathOf($links[0]->url));
        self::assertSame('cms_page', $links[0]->source);
        self::assertSame((int) $agency->getId(), $links[0]->sourceId);
        self::assertFalse($links[0]->opensInNewWindow);
        self::assertCount(1, $links[0]->children);
        self::assertSame('Equipe', $links[0]->children[0]->label);
        self::assertSame((int) $team->getId(), $links[0]->children[0]->sourceId);

        self::assertSame('Blog', $links[1]->label);
        self::assertSame('https://blog.example', $links[1]->url);
        self::assertSame('url', $links[1]->source);
        self::assertNull($links[1]->sourceId);
        self::assertTrue($links[1]->opensInNewWindow);
    }

    public function testTheFooterMenuFillsTheFooter(): void
    {
        $page = $this->createPage('Livraison et retours');
        $this->entry('footer', 'page', (int) $page->getId(), position: 1);

        $links = $this->getService(CmsContentSlotResolver::class)->resolve(ContentSlots::FOOTER, $this->locale());

        self::assertNotNull($links);
        self::assertSame(['Livraison et retours'], array_map(static fn (ContentSlotLink $link): string => $link->label, $links));
        self::assertNull(
            $this->getService(CmsContentSlotResolver::class)->resolve(ContentSlots::HEADER, $this->locale()),
            'The footer menu says nothing about the header.',
        );
    }

    /**
     * The merchant filled the menu in: an entry that does not resolve in this
     * language is left out, and the slot stays with the menu rather than going
     * back to the folders of the shop.
     */
    public function testAMenuWhoseEntriesAllFailStillOwnsTheSlot(): void
    {
        $draft = $this->createPage('Brouillon', published: false);
        $this->entry('main', 'page', (int) $draft->getId(), position: 1);

        self::assertSame([], $this->getService(CmsContentSlotResolver::class)->resolve(ContentSlots::HEADER, $this->locale()));
    }

    public function testTheConsentSlotsStayWithTheContents(): void
    {
        $page = $this->createPage('Conditions generales');
        $this->entry('main', 'page', (int) $page->getId(), position: 1);
        $this->entry('footer', 'page', (int) $page->getId(), position: 1);

        $resolver = $this->getService(CmsContentSlotResolver::class);

        self::assertNull($resolver->resolve(ContentSlots::CONSENT_PREFIX.'terms_and_conditions', $this->locale()));
        self::assertNull($resolver->resolve('some_slot_nobody_declared', $this->locale()));
    }

    /**
     * Read through the service of the core, the way a theme reads it: the
     * resolver of the module answers before the one of the contents.
     */
    public function testTheModuleAnswersBeforeTheContentsOfTheShop(): void
    {
        $page = $this->createPage('Nos services');
        $this->entry('main', 'page', (int) $page->getId(), position: 1);

        $links = $this->getService(ContentSlotService::class)->links(ContentSlots::HEADER, $this->locale());

        self::assertSame(['Nos services'], array_map(static fn (ContentSlotLink $link): string => $link->label, $links));
        self::assertSame('cms_page', $links[0]->source);
    }

    private function emptyMenu(string $code): void
    {
        $menu = CmsMenuQuery::create()->findOneByCode($code);

        if (!$menu instanceof CmsMenu) {
            $menu = (new CmsMenu())->setCode($code);
            $menu->save();
        }

        CmsMenuItemQuery::create()->filterByMenuId($menu->getId())->find()->delete();
        $this->getService(MenuCache::class)->invalidate();
    }

    private function entry(
        string $menuCode,
        string $type,
        ?int $targetId,
        int $parent = 0,
        int $position = 1,
        ?string $url = null,
        ?string $label = null,
        bool $newTab = false,
    ): CmsMenuItem {
        $menu = CmsMenuQuery::create()->findOneByCode($menuCode);
        self::assertInstanceOf(CmsMenu::class, $menu);

        $item = (new CmsMenuItem())
            ->setMenuId($menu->getId())
            ->setParent($parent)
            ->setPosition($position)
            ->setTargetType($type)
            ->setTargetId($targetId)
            ->setUrl($url)
            ->setOpenNewTab($newTab ? 1 : 0);

        if (null !== $label) {
            $item->setLocale($this->locale())->setLabel($label);
        }

        $item->save();
        $this->getService(MenuCache::class)->invalidate();

        return $item;
    }

    private function pathOf(?string $url): string
    {
        return ltrim((string) parse_url((string) $url, \PHP_URL_PATH), '/');
    }
}
