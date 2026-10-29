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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Core\SharedCache\SharedCacheContributorInterface;
use Thelia\Core\SharedCache\SharedCacheInvalidationListener;
use Thelia\Core\SharedCache\SharedCacheKeyCollector;
use Thelia\Core\SharedCache\SharedCacheMode;
use Thelia\Core\SharedCache\SharedCachePolicy;
use Thelia\Core\SharedCache\SharedCachePurgerInterface;
use Thelia\Model\ConfigQuery;
use TheliaCMS\Http\CachePurger;
use TheliaCMS\Http\CacheTags;
use TheliaCMS\Http\PageCacheListener;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\TheliaCMS;

/**
 * Once the shop turns the shared cache of the core on, the pages of the module are
 * shared by the core under the tags of the module, and every purge of the module
 * reaches the proxy of the core.
 */
final class CoreSharedCacheTest extends CmsIntegrationTestCase
{
    /** @var SharedCachePurgerInterface&object{calls: list<list<string>>} */
    private SharedCachePurgerInterface $purger;

    protected function setUp(): void
    {
        parent::setUp();

        if (!interface_exists(SharedCacheContributorInterface::class)) {
            self::markTestSkipped('The core of this shop predates the shared cache of the public pages.');
        }

        $this->purger = new class implements SharedCachePurgerInterface {
            /** @var list<list<string>> */
            public array $calls = [];

            public function purge(array $keys): void
            {
                $this->calls[] = $keys;
            }
        };
        static::getContainer()->set(SharedCachePurgerInterface::class, $this->purger);
        $this->collector()->reset();
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testThePagesOfTheModuleAreAViewTheCoreShares(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_view', TheliaCMS::PAGE_VIEW);

        self::assertTrue($this->getService(SharedCachePolicy::class)->isTaggable($request, new Response()));
    }

    public function testAPageOfTheModuleIsFiledUnderItsTagsAndTheCoreWritesItsHeaders(): void
    {
        ConfigQuery::write(SharedCacheMode::CONFIG_KEY, SharedCacheMode::ENABLED->value);
        $response = $this->respond(CacheTags::forPage(12));

        self::assertSame([CacheTags::SITE, CacheTags::MENUS, CacheTags::page(12)], $this->collector()->keys());
        self::assertFalse($response->headers->has('Cache-Tag'));
        self::assertNotSame('private, no-store', $response->headers->get('Cache-Control'));
    }

    public function testAnyOtherPageIsFiledUnderTheMenusAndTheSettingsOfTheModule(): void
    {
        ConfigQuery::write(SharedCacheMode::CONFIG_KEY, SharedCacheMode::ANNOUNCE->value);

        $this->respond(null);

        self::assertSame([CacheTags::SITE, CacheTags::MENUS], $this->collector()->keys());
    }

    public function testTheModuleKeepsItsOwnHeadersWhileTheShopDoesNotShare(): void
    {
        ConfigQuery::write(SharedCacheMode::CONFIG_KEY, SharedCacheMode::DISABLED->value);

        $response = $this->respond(CacheTags::forPage(12));

        self::assertSame([], $this->collector()->keys());
        self::assertSame(implode(',', CacheTags::forPage(12)), $response->headers->get('Cache-Tag'));
    }

    public function testAPurgeOfTheModuleReachesTheProxyOfTheCore(): void
    {
        $this->getService(CachePurger::class)->purge([CacheTags::page(12)]);
        $this->getService(SharedCacheInvalidationListener::class)->flush();

        self::assertSame([[CacheTags::page(12)]], $this->purger->calls);
    }

    /**
     * @param list<string>|null $tags
     */
    private function respond(?array $tags): Response
    {
        $request = Request::create('/');
        if (null !== $tags) {
            $request->attributes->set(PageCacheListener::TAGS_ATTRIBUTE, $tags);
        }
        $response = new Response('<html></html>');

        $this->getService(PageCacheListener::class)->onKernelResponse(
            new ResponseEvent(static::$kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response),
        );

        return $response;
    }

    private function collector(): SharedCacheKeyCollector
    {
        return $this->getService(SharedCacheKeyCollector::class);
    }
}
