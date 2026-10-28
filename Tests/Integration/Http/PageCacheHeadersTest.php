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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Core\SharedCache\SharedCacheMode;
use Thelia\Model\ConfigQuery;
use TheliaCMS\Http\CacheTags;
use TheliaCMS\Http\PageCacheListener;
use TheliaCMS\Http\PageCachePolicy;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\TheliaCMS;
use TheliaCMS\Twig\CmsExtension;

/**
 * The headers the module writes on its pages while the shop does not share
 * them itself: what a page is filed under, and what a cache keys it on.
 */
final class PageCacheHeadersTest extends CmsIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(SharedCacheMode::class)) {
            ConfigQuery::write(SharedCacheMode::CONFIG_KEY, SharedCacheMode::DISABLED->value);
        }
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testAPageOfTheModuleIsFiledUnderTheListsItShows(): void
    {
        $response = $this->respond(CacheTags::forPage(12), [CacheTags::PAGE_LISTS]);

        self::assertSame('cms,cms-menu,cms-page-12,cms-page-list', $response->headers->get('Cache-Tag'));
    }

    /**
     * A page of the shop may show a list of pages through a template: it is
     * not the module's to decide whether that page may be shared.
     */
    public function testAPageOfTheShopThatShowsAListKeepsTheHeadersOfTheShop(): void
    {
        $response = $this->respond(null, [CacheTags::PAGE_LISTS]);

        self::assertFalse($response->headers->has('Cache-Tag'));
        self::assertFalse($response->headers->has('Surrogate-Key'));
    }

    /**
     * The language of a page is in its address, its domain or its `lang`
     * parameter, never in the `Accept-Language` of the browser: varying on it
     * only keeps one copy of the same page per browser language.
     */
    public function testASharedPageDoesNotVaryOnTheLanguageOfTheBrowser(): void
    {
        $ttl = TheliaCMS::getConfigValue(PageCachePolicy::TTL_SETTING, '0');
        TheliaCMS::setConfigValue(PageCachePolicy::TTL_SETTING, '600');

        try {
            $response = $this->respond(CacheTags::forPage(12));
        } finally {
            TheliaCMS::setConfigValue(PageCachePolicy::TTL_SETTING, (string) $ttl);
        }

        self::assertSame('600', (string) $response->headers->getCacheControlDirective('s-maxage'), 'The page was not shared: the test proves nothing.');
        self::assertNotContains('Accept-Language', $response->getVary());
    }

    public function testAListReadFromATemplateFilesThePageUnderIt(): void
    {
        // The kernel of the suite may already hold a main request: the tags
        // go to that one, as they would to the page being served.
        $requests = $this->getService(RequestStack::class);
        $pushed = null === $requests->getMainRequest();

        if ($pushed) {
            $requests->push(Request::create('/recettes'));
        }

        $request = $requests->getMainRequest();
        self::assertInstanceOf(Request::class, $request);
        $request->attributes->remove(PageCacheListener::SHOWN_TAGS_ATTRIBUTE);

        try {
            $this->getService(CmsExtension::class)->pagesTagged('Recette');
        } finally {
            if ($pushed) {
                $requests->pop();
            }
        }

        self::assertSame([CacheTags::PAGE_LISTS], $request->attributes->get(PageCacheListener::SHOWN_TAGS_ATTRIBUTE));
    }

    /**
     * @param list<string>|null $tags
     * @param list<string>      $shown
     */
    private function respond(?array $tags, array $shown = []): Response
    {
        $request = Request::create('/a-page');
        if (null !== $tags) {
            $request->attributes->set(PageCacheListener::TAGS_ATTRIBUTE, $tags);
        }
        if ([] !== $shown) {
            $request->attributes->set(PageCacheListener::SHOWN_TAGS_ATTRIBUTE, $shown);
        }
        $response = new Response('<html></html>');

        $this->getService(PageCacheListener::class)->onKernelResponse(
            new ResponseEvent(static::$kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response),
        );

        return $response;
    }
}
