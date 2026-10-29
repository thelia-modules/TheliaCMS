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

namespace TheliaCMS\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use TheliaCMS\Preview\ItemDraftRequest;
use TheliaCMS\Preview\ItemDraftResponse;

/**
 * A page that showed the draft of an item stays out of caches and indexes;
 * any other page keeps the headers it was given.
 */
final class ItemDraftResponseTest extends TestCase
{
    public function testAPageThatShowedADraftIsNeitherCachedNorIndexed(): void
    {
        $request = new Request();
        $request->attributes->set(ItemDraftRequest::SERVED_ATTRIBUTE, true);

        $response = $this->answer($request, HttpKernelInterface::MAIN_REQUEST);

        self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'), 'The public cache the page was given is taken back.');
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'), 'The address carries the token.');
    }

    public function testAnyOtherPageKeepsItsHeaders(): void
    {
        $response = $this->answer(new Request(), HttpKernelInterface::MAIN_REQUEST);

        self::assertNull($response->headers->get('X-Robots-Tag'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
    }

    private function answer(Request $request, int $type): Response
    {
        $response = new Response('page');
        $response->setPublic();
        $response->setSharedMaxAge(600);

        (new ItemDraftResponse())->onKernelResponse(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, $type, $response));

        return $response;
    }
}
