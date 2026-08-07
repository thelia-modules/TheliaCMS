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

namespace TheliaCMS\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use TheliaCMS\Http\CacheTags;
use TheliaCMS\Http\PageCachePolicy;

/**
 * Answering "yes" here wrongly hands one visitor's page, and the cookie that
 * came with it, to the next person. Every "no" below is a way that happens.
 */
final class PageCachePolicyTest extends TestCase
{
    private PageCachePolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new PageCachePolicy();
    }

    public function testAPlainReadFromSomebodyWithNoSessionMayBeShared(): void
    {
        self::assertTrue($this->policy->isCacheable(Request::create('/a-page'), new Response()));
    }

    public function testAVisitorWhoArrivedWithASessionGetsTheirOwnAnswer(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/a-page');
        $request->setSession($session);
        $request->cookies->set($session->getName(), 'whatever');

        self::assertFalse($this->policy->isCacheable($request, new Response()));
    }

    /**
     * Thelia opens a session on every front-office request, so the answer
     * carries a cookie even for a visitor who never had one. Caching it would
     * give the next visitor that session.
     */
    public function testAnAnswerThatSetsACookieIsNeverShared(): void
    {
        $response = new Response();
        $response->headers->setCookie(Cookie::create('PHPSESSID', 'abc'));

        self::assertFalse($this->policy->isCacheable(Request::create('/a-page'), $response));
    }

    public function testOnlyASuccessfulAnswerIsShared(): void
    {
        self::assertFalse($this->policy->isCacheable(
            Request::create('/a-page'),
            new Response('', Response::HTTP_NOT_FOUND),
        ));
    }

    public function testAWriteIsNeverShared(): void
    {
        self::assertFalse($this->policy->isCacheable(Request::create('/a-page', 'POST'), new Response()));
    }

    /**
     * The tags are written whether the page may be shared or not: a proxy
     * configured to cache these pages needs them to drop the right ones later.
     */
    public function testTheTagsAreWrittenEvenWhenNothingMayBeCached(): void
    {
        $response = new Response();
        $response->headers->setCookie(Cookie::create('PHPSESSID', 'abc'));

        $this->policy->apply($response, Request::create('/a-page'), CacheTags::forPage(12));

        self::assertSame('cms,cms-menu,cms-page-12', $response->headers->get('Cache-Tag'));
        self::assertSame('cms cms-menu cms-page-12', $response->headers->get('Surrogate-Key'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
