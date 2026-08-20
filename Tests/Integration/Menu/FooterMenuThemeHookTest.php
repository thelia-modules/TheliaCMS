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

use TheliaCMS\Menu\FooterMenuThemeHook;
use TheliaCMS\Menu\MenuCache;
use TheliaCMS\Model\CmsMenu;
use TheliaCMS\Model\CmsMenuItem;
use TheliaCMS\Model\CmsMenuItemQuery;
use TheliaCMS\Model\CmsMenuQuery;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\TheliaCMS;

/**
 * Whether the footer of a theme that knows nothing about the CMS gets the menu.
 *
 * The decision is a setting, and a setting nobody can see the effect of is a
 * setting that silently stops working: measured against real rows, on the hook
 * the theme actually fires.
 */
final class FooterMenuThemeHookTest extends CmsIntegrationTestCase
{
    private ?string $previousSetting = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousSetting = TheliaCMS::getConfigValue(FooterMenuThemeHook::SETTING);
    }

    protected function tearDown(): void
    {
        TheliaCMS::setConfigValue(FooterMenuThemeHook::SETTING, $this->previousSetting);

        parent::tearDown();
    }

    public function testTheMenuIsLeftOutUntilTheSettingIsTurnedOn(): void
    {
        $this->footerMenuPointingAt($this->createPage('Livraison et retours'));

        TheliaCMS::setConfigValue(FooterMenuThemeHook::SETTING, '0');

        // A companion theme renders the menu itself; rendering it here as well
        // would show it twice.
        self::assertSame('', $this->render());
    }

    public function testTheMenuIsWrittenIntoTheFooterOnceTurnedOn(): void
    {
        $page = $this->createPage('Livraison et retours');
        $this->footerMenuPointingAt($page);

        TheliaCMS::setConfigValue(FooterMenuThemeHook::SETTING, '1');

        $markup = $this->render();

        self::assertStringContainsString('cms-menu--footer', $markup);
        self::assertStringContainsString((string) $page->getRewrittenUrl($this->locale()), $markup);
        self::assertStringContainsString('Livraison et retours', $markup);
    }

    public function testAFooterMenuWithNoEntryGetsNothing(): void
    {
        TheliaCMS::setConfigValue(FooterMenuThemeHook::SETTING, '1');

        $menu = CmsMenuQuery::create()->findOneByCode('footer');
        self::assertNotNull($menu, 'The seeder creates the footer menu; without it this test measures nothing.');

        CmsMenuItemQuery::create()->filterByMenuId($menu->getId())->find()->delete();

        // The nodes are cached per menu, and the cache is what a screen
        // invalidates when it writes: emptying the rows underneath it is not
        // enough, as a stale footer full of dead links would show.
        $this->getService(MenuCache::class)->invalidate();

        self::assertSame('', $this->render());
    }

    private function footerMenuPointingAt(object $page): void
    {
        $menu = CmsMenuQuery::create()->findOneByCode('footer');

        if (!$menu instanceof CmsMenu) {
            $menu = (new CmsMenu())->setCode('footer');
            $menu->save();
        }

        $item = (new CmsMenuItem())
            ->setMenuId($menu->getId())
            ->setParent(0)
            ->setPosition(1)
            ->setTargetType('page')
            ->setTargetId((int) $page->getId());

        $item->save();
    }

    private function render(): string
    {
        return $this->getService(FooterMenuThemeHook::class)->render('layout.footer.top', []);
    }
}
