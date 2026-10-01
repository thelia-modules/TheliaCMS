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

namespace TheliaCMS\Tests\Integration\BackOffice;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\BackOffice\BackOfficeNavigation;
use Thelia\Test\Trait\LogsInAsAdmin;
use TheliaCMS\Hook\CmsSideNavHook;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * Folders move into the CMS section of the back-office menu.
 *
 * The contents keep living where they are (the news of a site are contents),
 * so the screen stays; what goes is the section of its own, which would leave
 * a shop with two places for its content.
 */
final class FolderNavigationTest extends CmsIntegrationTestCase
{
    use LogsInAsAdmin;

    protected function tearDown(): void
    {
        $this->logoutAdmin();

        parent::tearDown();
    }

    public function testTheFolderSectionOfTheThemeIsHidden(): void
    {
        $navigation = $this->getService(BackOfficeNavigation::class);

        self::assertFalse($navigation->isSectionVisible('folder'));
        self::assertTrue($navigation->isSectionVisible('catalog'), 'Only the folders are taken over.');
    }

    public function testTheCmsSectionLinksToTheFolders(): void
    {
        $this->loginAsAdminInSession();

        $markup = $this->renderSideNav();
        $foldersUrl = $this->getService(UrlGeneratorInterface::class)->generate('admin.folders.default');

        self::assertStringContainsString('href="'.$foldersUrl.'"', $markup);
        self::assertStringContainsString('data-testid="bo-nav-cms-folders"', $markup);
    }

    /**
     * The folders were reachable from a section of their own: a profile allowed
     * to open them and nothing of the CMS must not lose its way to them when
     * that section goes.
     */
    public function testAProfileAllowedOnlyOnTheFoldersStillReachesThem(): void
    {
        $factory = $this->createFixtureFactory();
        $profile = $factory->profile();
        $factory->profileResource($profile, AdminResources::FOLDER);
        $this->loginAsAdminInSession($factory->admin(['profile' => $profile]));

        $markup = $this->renderSideNav();

        self::assertStringContainsString('data-testid="bo-nav-cms-folders"', $markup);
        self::assertStringNotContainsString('href="'.$this->getService(UrlGeneratorInterface::class)->generate('admin.cms.pages.list').'"', $markup, 'The pages stay closed to that profile.');
    }

    public function testAProfileWithoutTheFoldersDoesNotSeeTheEntry(): void
    {
        $factory = $this->createFixtureFactory();
        $profile = $factory->profile();
        $factory->profileResource($profile, CmsResources::PAGE);
        $this->loginAsAdminInSession($factory->admin(['profile' => $profile]));

        $markup = $this->renderSideNav();

        self::assertStringContainsString('data-testid="bo-nav-cms"', $markup);
        self::assertStringNotContainsString('data-testid="bo-nav-cms-folders"', $markup, 'A link leading straight to a 403 is worse than no link.');
    }

    private function renderSideNav(): string
    {
        $event = new HookRenderEvent('main.in-top-menu-items');
        $this->getService(CmsSideNavHook::class)->onMainInTopMenuItems($event);

        return $event->dump();
    }
}
