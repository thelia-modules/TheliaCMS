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

namespace TheliaCMS\Tests\Integration\Vitrine;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Model\ConfigQuery;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\TheliaCMS;
use TheliaCMS\Vitrine\NotFoundPageListener;

/**
 * The page a site shows on an address that does not exist.
 *
 * An address that used to hold a page keeps its id: what surrounds the page
 * being served is resolved from the request, so the page shown and everything
 * that describes it have to agree.
 */
final class NotFoundPageTest extends CmsIntegrationTestCase
{
    private ?string $previousSetting = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousSetting = TheliaCMS::getConfigValue('404_page_id');
        $this->useAnInstalledFrontTheme();
    }

    protected function tearDown(): void
    {
        TheliaCMS::setConfigValue('404_page_id', $this->previousSetting);

        parent::tearDown();
    }

    public function testTheConfiguredPageIsServedWithTheNotFoundStatus(): void
    {
        $notFoundPage = $this->createPage('Page introuvable', html: '<h1>Cette page n’existe pas</h1>');
        TheliaCMS::setConfigValue('404_page_id', (string) $notFoundPage->getId());

        $event = $this->notFoundOn(Request::create('/une-adresse-qui-nexiste-pas'));
        $this->listener()->onKernelException($event);

        $response = $event->getResponse();

        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('noindex', $response->headers->get('X-Robots-Tag'));
        self::assertStringContainsString('Cette page n’existe pas', (string) $response->getContent());
    }

    public function testThePageServedIsTheOneTheRequestNamesAfterwards(): void
    {
        $gone = $this->createPage('Page retirée');
        $notFoundPage = $this->createPage('Page introuvable', html: '<h1>Cette page n’existe pas</h1>');
        TheliaCMS::setConfigValue('404_page_id', (string) $notFoundPage->getId());

        // What the rewriting router leaves behind for an address that still
        // resolves to a page nobody can open.
        $request = Request::create('/page-retiree?'.TheliaCMS::PAGE_VIEW.'_id='.$gone->getId());
        $request->attributes->set('_view', TheliaCMS::PAGE_VIEW);

        $this->listener()->onKernelException($this->notFoundOn($request));

        // Read the way SEOne reads it, which is what builds the breadcrumb, its
        // BreadcrumbList and the title of the page.
        self::assertSame(TheliaCMS::PAGE_VIEW, $request->get('_view'));
        self::assertSame(
            (string) $notFoundPage->getId(),
            (string) $request->get(TheliaCMS::PAGE_VIEW.'_id'),
            'The page served is the 404 page, and the breadcrumb names the page that was asked for.',
        );
    }

    public function testAnAddressIsLeftAloneWhenNoPageIsConfigured(): void
    {
        TheliaCMS::setConfigValue('404_page_id', '');

        $request = Request::create('/une-adresse-qui-nexiste-pas');
        $event = $this->notFoundOn($request);

        $this->listener()->onKernelException($event);

        self::assertNull($event->getResponse(), 'The theme keeps the hand when the site has no 404 page.');
        self::assertNull($request->attributes->get(TheliaCMS::PAGE_VIEW.'_id'));
    }

    public function testTheBackOfficeKeepsItsOwnAnswer(): void
    {
        $notFoundPage = $this->createPage('Page introuvable');
        TheliaCMS::setConfigValue('404_page_id', (string) $notFoundPage->getId());

        $event = $this->notFoundOn(Request::create('/admin/cms/pages/does-not-exist'));

        $this->listener()->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    /**
     * The 404 page is rendered through the front theme, so the shop needs one
     * it actually has.
     *
     * `bin/test-prepare` seeds the theme name of a stock install, which is not
     * necessarily the theme installed here: left alone, the renderer resolves a
     * parser on a directory that does not exist and every test below fails on
     * something that has nothing to do with what it measures. The write is
     * undone with the transaction of the test.
     */
    private function useAnInstalledFrontTheme(): void
    {
        $helper = $this->getService(TemplateHelperInterface::class);

        if (is_dir($helper->getActiveFrontTemplate()->getAbsolutePath())) {
            return;
        }

        $installed = $helper->getList(TemplateDefinition::FRONT_OFFICE);

        if ([] === $installed) {
            self::markTestSkipped('The shop has no front theme installed, so no page can be rendered.');
        }

        ConfigQuery::write('active-front-template', $installed[0]->getName());
    }

    private function listener(): NotFoundPageListener
    {
        return $this->getService(NotFoundPageListener::class);
    }

    private function notFoundOn(Request $request): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->getService(KernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException(),
        );
    }
}
