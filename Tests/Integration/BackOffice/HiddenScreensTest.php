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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Test\Trait\LogsInAsAdmin;
use TheliaCMS\Hook\CmsDashboardHook;
use TheliaCMS\Hook\CmsSideNavHook;
use TheliaCMS\Security\CmsAdminGuard;
use TheliaCMS\Settings\CmsSettings;
use TheliaCMS\Settings\SiteMode;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\TheliaCMS;

/**
 * The scripts and the site styles screens are kept out of the back office.
 *
 * Nothing of what they configure changes: the snippets already saved keep being
 * written into the pages, and the custom-code permission keeps deciding who may
 * put free HTML into a page. Only the screens go, from the menu, from the
 * dashboard and from a typed address.
 */
final class HiddenScreensTest extends CmsIntegrationTestCase
{
    use LogsInAsAdmin;

    private ?string $previousMode = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousMode = TheliaCMS::getConfigValue(CmsSettings::SITE_MODE);
        $this->loginAsAdminInSession();
    }

    protected function tearDown(): void
    {
        $this->logoutAdmin();
        TheliaCMS::setConfigValue(CmsSettings::SITE_MODE, $this->previousMode ?? SiteMode::Commerce->value);

        parent::tearDown();
    }

    public function testTheMenuOffersNeitherScreen(): void
    {
        $event = new HookRenderEvent('main.in-top-menu-items');
        $this->getService(CmsSideNavHook::class)->onMainInTopMenuItems($event);
        $markup = $event->dump();

        self::assertStringContainsString('href="'.$this->url('admin.cms.settings.edit').'"', $markup, 'The settings stay.');
        self::assertStringNotContainsString($this->url('admin.cms.scripts.list'), $markup);
        self::assertStringNotContainsString($this->url('admin.cms.settings.styles.edit'), $markup);
    }

    public function testTheDashboardDoesNotSendToTheScripts(): void
    {
        TheliaCMS::setConfigValue(CmsSettings::SITE_MODE, SiteMode::Showcase->value);

        $event = new HookRenderEvent('home.top');
        $this->getService(CmsDashboardHook::class)->onHomeBlock($event);
        $markup = $event->dump();

        self::assertStringContainsString($this->url('admin.cms.pages.list'), $markup, 'The block is rendered on a showcase site.');
        self::assertStringNotContainsString($this->url('admin.cms.scripts.list'), $markup);
    }

    public function testATypedAddressOfEitherScreenAnswersNotFound(): void
    {
        foreach (['/admin/cms/scripts', '/admin/cms/scripts/new', '/admin/cms/scripts/3', '/admin/cms/settings/styles', '/admin/cms/settings/styles/fonts'] as $path) {
            try {
                $this->guard($path);
                self::fail(\sprintf('%s is still reachable.', $path));
            } catch (NotFoundHttpException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTheOtherScreensStayReachable(): void
    {
        $this->guard('/admin/cms/settings');
        $this->guard('/admin/cms/pages');
        $this->guard('/admin/cms/scripts-of-another-module');

        self::addToAssertionCount(1);
    }

    private function guard(string $path): void
    {
        $event = new ControllerEvent(
            $this->getService(KernelInterface::class),
            static fn (): null => null,
            Request::create($path),
            HttpKernelInterface::MAIN_REQUEST,
        );

        $this->getService(CmsAdminGuard::class)->onKernelController($event);
    }

    private function url(string $route): string
    {
        return $this->getService(UrlGeneratorInterface::class)->generate($route);
    }
}
