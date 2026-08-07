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

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\RewritingUrl;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\TheliaCMS;
use TheliaCMS\Vitrine\TrailingSlashRedirectListener;

/**
 * An address that differs from a real one only by its trailing slash.
 *
 * WordPress, Drupal and Prestashop all serve their addresses with the slash, so
 * a site moved over arrives with it on every indexed address and every inbound
 * link. The rewriting router answers 404 on those, which throws away the ranking
 * of a whole site at once.
 *
 * The addresses are read back from the pages rather than written here: the shop
 * seeds legal pages of its own, and a fixture asking for a slug already taken
 * gets the next one free.
 *
 * The requests are Thelia requests and not plain Symfony ones. That is not a
 * detail: with `allow_slash_ended_uri` on, the Thelia request drops the trailing
 * slash from its own `getPathInfo()`, so a test built on a Symfony request runs
 * against a path the runtime never sees and passes while the site still answers
 * 404.
 */
final class TrailingSlashRedirectTest extends CmsIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Whether the theme renders a view is part of the answer below.
        $this->useAnInstalledFrontTheme();
    }

    /**
     * A view of another module, which this module cannot interrogate.
     *
     * The address exists and the theme renders that view, so the redirection
     * happens: contents and folders taken over from another CMS are the reason
     * any of this exists, and they are not pages of this module.
     */
    public function testAnAddressOfAnotherViewTheThemeRendersIsRedirected(): void
    {
        $page = $this->createPage('Support de la reecriture');
        $this->addressPointingAt('un-article-repris', $page, 'content');

        $response = $this->answerFor('/un-article-repris/');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('http://localhost/un-article-repris', $response->getTargetUrl());
    }

    /**
     * A view no theme renders answers 404 for every address pointing at it, so
     * there is nothing on the other side to send a visitor to. The theme of a
     * content-only site ships no `product.html.twig`, and every product address
     * of the shop it grew out of is in that state.
     */
    public function testAnAddressOfAViewTheThemeCannotRenderIsLeftAlone(): void
    {
        $page = $this->createPage('Support de la reecriture');
        $this->addressPointingAt('une-vue-sans-gabarit', $page, 'gabarit-absent');

        self::assertNull($this->answerFor('/une-vue-sans-gabarit/'));
    }

    /**
     * An accented address arrives percent encoded and is stored decoded.
     *
     * `rewriting_url.url` holds the bytes the address decodes to, while the path
     * of the request is what was written on the wire. Comparing the two as they
     * come leaves every accented address of a French site answering 404 on its
     * slash, which is most of them.
     */
    public function testAnAccentedAddressIsRecognisedThoughItArrivesEncoded(): void
    {
        $page = $this->createPage('Support de la reecriture');
        $this->addressPointingAt('salle-à-manger', $page, 'content');

        $response = $this->answerFor('/salle-%C3%A0-manger/');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('http://localhost/salle-%C3%A0-manger', $response->getTargetUrl());
    }

    public function testAnAddressWithATrailingSlashGoesToTheOneWithout(): void
    {
        $address = $this->addressOf($this->createPage('Notre histoire depuis 1998'));

        $response = $this->answerFor('/'.$address.'/');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode(), 'A 302 passes no ranking on.');
        self::assertSame('http://localhost/'.$address, $response->getTargetUrl());
    }

    /**
     * The host is the one the visitor reached, not the one configured as the
     * default URI: a site answering on several domains would otherwise send
     * every visitor to whichever one is written in the configuration.
     */
    public function testTheRedirectionStaysOnTheHostThatWasAskedFor(): void
    {
        $address = $this->addressOf($this->createPage('Notre histoire depuis 1998'));

        $response = $this->answerFor('https://www.exemple.test/'.$address.'/');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://www.exemple.test/'.$address, $response->getTargetUrl());
    }

    public function testTheQueryStringTravels(): void
    {
        $address = $this->addressOf($this->createPage('Notre histoire depuis 1998'));

        $response = $this->answerFor('/'.$address.'/?utm_source=lettre&page=2');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('http://localhost/'.$address.'?utm_source=lettre&page=2', $response->getTargetUrl());
    }

    /**
     * The target of the redirection carries no trailing slash, so a second pass
     * over it produces nothing: one hop, and no chain a browser would refuse.
     */
    public function testTheAddressRedirectedToDoesNotRedirectAgain(): void
    {
        $address = $this->addressOf($this->createPage('Notre histoire depuis 1998'));

        $first = $this->answerFor('/'.$address.'/');
        self::assertInstanceOf(RedirectResponse::class, $first);

        self::assertNull(
            $this->answerFor($first->getTargetUrl()),
            'The address redirected to would redirect again, which is a loop.',
        );
    }

    public function testSeveralTrailingSlashesCollapseInOneHop(): void
    {
        $address = $this->addressOf($this->createPage('Notre histoire depuis 1998'));

        $response = $this->answerFor('/'.$address.'///');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('http://localhost/'.$address, $response->getTargetUrl());
    }

    public function testAnAddressThatExistsNowhereIsLeftAlone(): void
    {
        self::assertNull(
            $this->answerFor('/une-adresse-qui-na-jamais-existe/'),
            'Redirecting to a 404 is worse than the 404 that was asked for.',
        );
    }

    /**
     * The bin, the drafts and the publication window are the same answer here as
     * anywhere else: the address exists in the rewriting table, and it answers
     * 404 all the same.
     */
    public function testAnAddressWhosePageNobodyCanOpenIsLeftAlone(): void
    {
        $address = $this->addressOf($this->createPage('Brouillon jamais publie', published: false));

        self::assertNull($this->answerFor('/'.$address.'/'));
    }

    /**
     * Two separate things keep the root out: the path is tested before anything
     * else, and an address made of slashes alone leaves no address to redirect
     * to. Breaking either one on its own therefore leaves this green, so read it
     * as the promise rather than as the measurement of one line.
     */
    public function testTheRootIsNeverRedirected(): void
    {
        self::assertNull($this->answerFor('/'), 'The home page is the one address that ends on a slash.');
    }

    /**
     * The back office and the API answer for themselves.
     *
     * The addresses are written straight into the rewriting table, because that
     * is the only way they occur: the back office refuses those slugs. A row put
     * there by an import or by another module is exactly what the guard is for,
     * since the rewriting router runs before the Symfony routes and would shadow
     * the back office.
     */
    public function testAnAddressUnderTheBackOfficeOrTheApiIsNeverRedirected(): void
    {
        $page = $this->createPage('Page glissee sous le back office');

        $this->addressPointingAt('admin/tableau-de-bord', $page);
        $this->addressPointingAt('api/pages', $page);

        self::assertNull($this->answerFor('/admin/tableau-de-bord/'));
        self::assertNull($this->answerFor('/api/pages/'));
    }

    /**
     * A page whose own address starts with those letters is not the back office.
     */
    public function testAPageAddressedLikeTheBackOfficeIsStillRedirected(): void
    {
        $address = $this->addressOf($this->createPage('Administration des ventes'));

        $response = $this->answerFor('/'.$address.'/');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('http://localhost/'.$address, $response->getTargetUrl());
    }

    public function testAPostIsLeftAlone(): void
    {
        $address = $this->addressOf($this->createPage('Notre histoire depuis 1998'));

        self::assertNull(
            $this->answerFor('/'.$address.'/', 'POST'),
            'A redirected POST loses its body, so the 404 that was asked for is the honest answer.',
        );
    }

    public function testAnAddressWithoutATrailingSlashIsNotTouched(): void
    {
        $address = $this->addressOf($this->createPage('Notre histoire depuis 1998'));

        self::assertNull($this->answerFor('/'.$address));
    }

    private function addressPointingAt(string $url, CmsPage $page, ?string $view = null): void
    {
        (new RewritingUrl())
            ->setUrl($url)
            ->setView($view ?? TheliaCMS::PAGE_VIEW)
            ->setViewId((string) $page->getId())
            ->setViewLocale($this->locale())
            ->save();
    }

    private function addressOf(CmsPage $page): string
    {
        $address = (string) $page->getRewrittenUrl($this->locale());

        self::assertNotSame('', $address, 'The page has to have an address for any of this to measure anything.');

        return $address;
    }

    private function answerFor(string $uri, string $method = 'GET'): ?Response
    {
        $event = new ExceptionEvent(
            $this->getService(KernelInterface::class),
            Request::create($uri, $method),
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException(),
        );

        $this->getService(TrailingSlashRedirectListener::class)->onKernelException($event);

        return $event->getResponse();
    }
}
