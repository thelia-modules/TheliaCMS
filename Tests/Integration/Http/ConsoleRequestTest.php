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

namespace TheliaCMS\Tests\Integration\Http;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;
use TheliaCMS\Http\ConsoleRequest;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * A command renders a page as a visit to the shop would: links and images on
 * the address of the shop, never on a host it made up.
 */
final class ConsoleRequestTest extends CmsIntegrationTestCase
{
    public function testTheWorkRunsAsAVisitToTheAddressOfTheShop(): void
    {
        $requests = new RequestStack();
        $context = new RequestContext();
        $console = new ConsoleRequest($requests, $context, $this->getService(URL::class));

        $seen = $console->around(static fn (): array => [
            $requests->getMainRequest()?->getSchemeAndHttpHost(),
            $requests->getMainRequest()?->getSession()->isStarted() ?? null,
            $context->getScheme().'://'.$context->getHost(),
        ], 'https://boutique.example/');

        self::assertSame(['https://boutique.example', false, 'https://boutique.example'], $seen, 'The links read the stack, the images the router: both are on the shop.');
        self::assertNull($requests->getMainRequest(), 'The request is taken off afterwards.');
        self::assertSame('http://localhost', $context->getScheme().'://'.$context->getHost(), 'The router is given its context back.');
    }

    public function testWithoutAnAddressNothingIsMadeUpAndTheFailureSaysWhatIsMissing(): void
    {
        ConfigQuery::write('url_site', '');
        $requests = new RequestStack();
        $console = new ConsoleRequest($requests, new RequestContext(), $this->getService(URL::class));

        self::assertNull($console->around(static fn (): ?string => $requests->getMainRequest()?->getHost()), 'No host is made up.');

        $this->expectExceptionMessage('set url_site in the configuration, or pass --url');
        $console->around(static function (): never {
            throw new \RuntimeException('No current request found');
        });
    }
}
