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
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\TheliaCMS;
use TheliaCMS\Vitrine\NotFoundPageListener;

/**
 * The page a site shows on an address that does not exist.
 *
 * An address that used to hold a page keeps its id: what surrounds the page
 * being served is resolved from the request, so the page shown and everything
 * that describes it have to agree.
 *
 * What the listener undoes when a render fails is covered by reading its source
 * instead (StandInPageListenerSymmetryTest): the renderer falls back to the
 * template shipped with the module rather than failing, so no test driving the
 * listener can reach that path.
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

    /**
     * The canonical URL, the hreflang tags and the language switcher all read
     * the id from the query string rather than through `Request::get()`, so
     * naming the page in the attributes alone leaves them describing the address
     * that was asked for.
     */
    public function testWhatDescribesTheResponseReadsThePageServed(): void
    {
        $gone = $this->createPage('Page retirée');
        $notFoundPage = $this->createPage('Page introuvable', html: '<h1>Cette page n’existe pas</h1>');
        TheliaCMS::setConfigValue('404_page_id', (string) $notFoundPage->getId());

        $request = Request::create('/page-retiree?'.TheliaCMS::PAGE_VIEW.'_id='.$gone->getId());
        $request->attributes->set('_view', TheliaCMS::PAGE_VIEW);

        $this->listener()->onKernelException($this->notFoundOn($request));

        self::assertSame(
            (int) $notFoundPage->getId(),
            $request->query->getInt(TheliaCMS::PAGE_VIEW.'_id'),
            'The canonical URL is built from the query string, so it would otherwise name the missing address.',
        );
        self::assertSame(
            (int) $notFoundPage->getId(),
            $request->attributes->getInt(TheliaCMS::STAND_IN_PAGE_ATTRIBUTE),
            'Nothing tells the canonical listener the response is a stand-in.',
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
