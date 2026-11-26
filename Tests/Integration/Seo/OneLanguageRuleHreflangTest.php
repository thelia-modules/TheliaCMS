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

namespace TheliaCMS\Tests\Integration\Seo;

use SEOne\Event\AlternateHreflangEvent;
use SEOne\Service\SeoToolsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\LangQuery;
use TheliaCMS\Locale\AlternateUrls;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\Tests\Integration\PublishesInOtherLanguages;
use TheliaCMS\TheliaCMS;

/**
 * One rule for what a page advertises: a language where the page is noindex or
 * not visible is left out of the hreflang tags (and of the sitemap, see
 * SitemapOneLanguageRuleTest). The language switcher is not advertising: it
 * keeps the languages the page exists in.
 */
final class OneLanguageRuleHreflangTest extends CmsIntegrationTestCase
{
    use PublishesInOtherLanguages;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(AlternateHreflangEvent::class)) {
            self::markTestSkipped('SEOne is not installed on this shop.');
        }

        $this->forgetLanguageCaches();
    }

    protected function tearDown(): void
    {
        $this->forgetLanguageCaches();

        parent::tearDown();
    }

    public function testALanguageWhereThePageIsNoindexIsLeftOutOfItsHreflang(): void
    {
        [$first, $second] = $this->twoOtherLocales();
        [$quiet, $open] = $this->twoPagesInThreeLanguages($first, $second);
        $this->markNoindexIn($quiet, $first);

        $quietTags = $this->hreflangOf($quiet);
        $openTags = $this->hreflangOf($open);

        self::assertStringNotContainsString($this->tag($first), $quietTags, 'noindex in that language: not advertised');
        self::assertStringContainsString($this->tag($second), $quietTags, 'the other language of the same page stays');
        self::assertStringContainsString($this->tag($this->locale()), $quietTags);
        self::assertStringContainsString($this->tag($first), $openTags, 'the same language of another page, not noindex, stays');
        self::assertStringContainsString($this->tag($second), $openTags);
    }

    public function testALanguageNotVisibleIsLeftOutOfTheAlternatesOfThePage(): void
    {
        [$first, $second] = $this->twoOtherLocales();
        [$one, $two] = $this->twoPagesInThreeLanguages($first, $second);
        $this->hideLanguage($first);

        foreach ([$one, $two] as $page) {
            $event = $this->alternateEventFor($page, $first);
            self::assertTrue('' === (string) $event->getUrl(), 'a language not visible has no alternate, whatever iterates it');
            self::assertNotSame('', (string) $this->alternateEventFor($page, $second)->getUrl());
        }
    }

    public function testALanguageNotActiveHasNoAlternateEither(): void
    {
        [$first, $second] = $this->twoOtherLocales();
        [$one] = $this->twoPagesInThreeLanguages($first, $second);
        LangQuery::create()->filterByLocale($first)->update(['Active' => 0]);
        $this->forgetLanguageCaches();

        self::assertSame('', (string) $this->alternateEventFor($one, $first)->getUrl());
        self::assertNotSame('', (string) $this->alternateEventFor($one, $second)->getUrl());
    }

    public function testTheSwitcherKeepsTheLanguageWhereThePageIsNoindex(): void
    {
        [$first, $second] = $this->twoOtherLocales();
        [$quiet, $open] = $this->twoPagesInThreeLanguages($first, $second);
        $this->markNoindexIn($quiet, $first);

        foreach ([$quiet, $open] as $page) {
            $locales = $this->inRequestOf($page, static fn (AlternateUrls $urls): array => array_column($urls->all(), 'locale'));

            self::assertContains($first, $locales, 'the page exists there and a visitor may go: only the advertising drops it');
            self::assertContains($second, $locales);
        }
    }

    public function testAPageNotPublishedInALanguageStaysOutOfItsHreflang(): void
    {
        [$first, $second] = $this->twoOtherLocales();
        $page = $this->createPage('Page en deux langues');
        $this->publishIn($page, $first, 'Page in the first language');

        $tags = $this->hreflangOf($page);

        self::assertStringContainsString($this->tag($first), $tags);
        self::assertStringNotContainsString($this->tag($second), $tags);
    }

    /**
     * @return array{0: CmsPage, 1: CmsPage}
     */
    private function twoPagesInThreeLanguages(string $first, string $second): array
    {
        $pages = [];

        foreach (['Page discrete', 'Page ouverte'] as $title) {
            $page = $this->createPage($title);
            $this->publishIn($page, $first, $title.' '.$first);
            $this->publishIn($page, $second, $title.' '.$second);
            $pages[] = $page;
        }

        return [$pages[0], $pages[1]];
    }

    private function tag(string $locale): string
    {
        return 'hreflang="'.$this->hreflangValue($locale).'"';
    }

    /**
     * The value SEOne writes for a locale, whichever format the shop is set to.
     */
    private function hreflangValue(string $locale): string
    {
        return strtolower(explode('_', $locale)[0]);
    }

    private function hreflangOf(CmsPage $page): string
    {
        return $this->inRequestOf($page, fn (): string => $this->getService(SeoToolsService::class)->getHreflang());
    }

    private function alternateEventFor(CmsPage $page, string $locale): AlternateHreflangEvent
    {
        return $this->inRequestOf($page, function () use ($locale): AlternateHreflangEvent {
            $lang = LangQuery::create()->filterByLocale($locale)->findOne();
            self::assertNotNull($lang);
            $event = new AlternateHreflangEvent($lang, $this->getService(RequestStack::class)->getMainRequest());
            $this->getService(EventDispatcherInterface::class)->dispatch($event, AlternateHreflangEvent::BASE_EVENT_NAME);

            return $event;
        });
    }

    /**
     * @template T
     *
     * @param callable(AlternateUrls): T $work
     *
     * @return T
     */
    private function inRequestOf(CmsPage $page, callable $work): mixed
    {
        $stack = $this->getService(RequestStack::class);
        $pushedByTheTestCase = $stack->pop();

        $request = Request::create('/'.$page->getId().'?'.TheliaCMS::PAGE_VIEW.'_id='.$page->getId());
        $request->attributes->set('_view', TheliaCMS::PAGE_VIEW);
        $request->attributes->set(TheliaCMS::PAGE_VIEW.'_id', (int) $page->getId());

        if ($pushedByTheTestCase?->hasSession()) {
            $request->setSession($pushedByTheTestCase->getSession());
        }

        $stack->push($request);

        try {
            return $work($this->getService(AlternateUrls::class));
        } finally {
            $stack->pop();

            if (null !== $pushedByTheTestCase) {
                $stack->push($pushedByTheTestCase);
            }
        }
    }
}
