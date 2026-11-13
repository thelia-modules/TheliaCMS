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

namespace TheliaCMS\Tests\Integration\Evaluation;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;
use TheliaCMS\Evaluation\ComponentBench\BenchShopRequest;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * The bench runs on the command line, where the components of the theme find
 * no request to read the catalogue through (`resources()` of the core): it
 * lends them a visit of the shop for the time of the round.
 *
 * Sabotage: open() returning without pushing; no session on the request.
 */
final class BenchShopRequestTest extends CmsIntegrationTestCase
{
    public function testARoundRunsInAVisitOfTheShopWhichEndsWithIt(): void
    {
        $stack = $this->getService(RequestStack::class);

        while (null !== $stack->pop()) {
        }

        $close = $this->getService(BenchShopRequest::class)->open();
        $request = $stack->getCurrentRequest();

        self::assertNotNull($request, 'The components of the theme find a request.');
        self::assertTrue($request->hasSession(), 'And its session, which the images of the library read their language from.');
        self::assertSame(parse_url((string) ConfigQuery::getConfiguredShopUrl(), \PHP_URL_HOST) ?: 'localhost', $request->getHost());
        self::assertInstanceOf(URL::class, URL::getInstance(), 'The URL service of the core is built.');

        $close();

        self::assertNull($stack->getCurrentRequest(), 'The visit ends with the round.');
    }

    public function testARequestAlreadyThereIsLeftAlone(): void
    {
        $stack = $this->getService(RequestStack::class);
        $mine = Request::create('/admin');
        $stack->push($mine);

        $close = $this->getService(BenchShopRequest::class)->open();
        self::assertSame($mine, $stack->getCurrentRequest());
        $close();

        self::assertSame($mine, $stack->pop());
    }
}
