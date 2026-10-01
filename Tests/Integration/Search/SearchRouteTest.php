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

namespace TheliaCMS\Tests\Integration\Search;

use Symfony\Cmf\Component\Routing\ChainRouterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use TheliaCMS\Settings\CmsSettings;
use TheliaCMS\Settings\SiteMode;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\TheliaCMS;

/**
 * Who answers on `/search` and `/recherche`.
 *
 * A shop keeps the product search of its theme: the search bar of the theme
 * posts to `/search`, and a page search answering there shows a visitor looking
 * for a chair an empty form about pages. Only a showcase site, which has no
 * catalogue to search, hands those paths to the pages.
 */
final class SearchRouteTest extends CmsIntegrationTestCase
{
    private const string ROUTE = 'cms.search';

    private ?string $previousMode = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousMode = TheliaCMS::getConfigValue(CmsSettings::SITE_MODE);
    }

    protected function tearDown(): void
    {
        TheliaCMS::setConfigValue(CmsSettings::SITE_MODE, $this->previousMode ?? SiteMode::Commerce->value);

        parent::tearDown();
    }

    public function testAShopKeepsTheSearchOfItsTheme(): void
    {
        TheliaCMS::setConfigValue(CmsSettings::SITE_MODE, SiteMode::Commerce->value);

        self::assertNotSame(self::ROUTE, $this->routeOf('/search'), 'The product search of the theme answers on a shop.');
        self::assertNotSame(self::ROUTE, $this->routeOf('/recherche'));
    }

    public function testAShowcaseSiteSearchesItsPages(): void
    {
        TheliaCMS::setConfigValue(CmsSettings::SITE_MODE, SiteMode::Showcase->value);

        self::assertSame(self::ROUTE, $this->routeOf('/search'));
        self::assertSame(self::ROUTE, $this->routeOf('/recherche'));
    }

    /**
     * The request goes through the router chain the front office matches with,
     * not through the Symfony router alone: the chain hands its own context to
     * every router in it, and a condition calling `service()` reads its
     * functions from that context.
     */
    public function testTheRouterChainOfTheFrontOfficeReadsTheSiteMode(): void
    {
        TheliaCMS::setConfigValue(CmsSettings::SITE_MODE, SiteMode::Commerce->value);
        self::assertNotSame(self::ROUTE, $this->routeThroughTheChainOf('/search'));

        TheliaCMS::setConfigValue(CmsSettings::SITE_MODE, SiteMode::Showcase->value);
        self::assertSame(self::ROUTE, $this->routeThroughTheChainOf('/search'));
    }

    private function routeOf(string $path): ?string
    {
        $router = $this->getService(RouterInterface::class);
        $previousContext = $router->getContext();

        // A copy of the context of the router rather than a new one: it carries
        // the functions route conditions call, `service()` among them.
        $router->setContext((clone $previousContext)->setMethod('GET')->setPathInfo($path));

        try {
            return $router->match($path)['_route'] ?? null;
        } catch (ResourceNotFoundException) {
            return null;
        } finally {
            $router->setContext($previousContext);
        }
    }

    private function routeThroughTheChainOf(string $path): ?string
    {
        $chain = static::getContainer()->get('router.chainRequest');
        self::assertInstanceOf(ChainRouterInterface::class, $chain);

        $request = Request::create($path);
        $previousContext = clone $chain->getContext();
        $chain->getContext()->fromRequest($request);

        try {
            return $chain->matchRequest($request)['_route'] ?? null;
        } catch (ResourceNotFoundException) {
            return null;
        } finally {
            $chain->setContext($previousContext);
        }
    }
}
