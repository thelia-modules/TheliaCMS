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

namespace TheliaCMS\Tests\Integration\Sitemap;

use TheliaCMS\Model\CmsPage;
use TheliaCMS\Sitemap\SitemapCache;
use TheliaCMS\Sitemap\SitemapThemeHook;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\Tests\Integration\PublishesInOtherLanguages;

/**
 * One rule for what a page advertises, shared with hreflang: a language where
 * the page is noindex or not visible gets neither an entry of its own nor an
 * `xhtml:link` alternate in the sitemap.
 */
final class SitemapOneLanguageRuleTest extends CmsIntegrationTestCase
{
    use PublishesInOtherLanguages;

    /** @var array<int, string> the base title of each page of the case */
    private array $titles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->forgetLanguageCaches();
        $this->emptyTheCache();
    }

    protected function tearDown(): void
    {
        $this->emptyTheCache();
        $this->forgetLanguageCaches();

        parent::tearDown();
    }

    public function testALanguageNotVisibleHasNoEntryAndNoAlternateForAnyPage(): void
    {
        [$shown, $hidden] = $this->twoOtherLocales();
        [$one, $two] = $this->twoPages($shown, $hidden);
        $this->hideLanguage($hidden);
        $this->emptyTheCache();

        $xml = $this->render();

        foreach ([$one, $two] as $page) {
            self::assertStringContainsString($this->slug($page, $shown), $xml, 'a visible language keeps its entry');
            self::assertStringNotContainsString($this->slug($page, $hidden), $xml, 'no entry, and no alternate, for a language not visible');
        }

        self::assertStringNotContainsString('hreflang="'.str_replace('_', '-', $hidden).'"', $xml);
        self::assertStringContainsString('hreflang="'.str_replace('_', '-', $shown).'"', $xml);
    }

    public function testALanguageNotVisibleHasNoEntryOfItsOwn(): void
    {
        [$shown, $hidden] = $this->twoOtherLocales();
        [$one] = $this->twoPages($shown, $hidden);
        $this->hideLanguage($hidden);
        $this->emptyTheCache();

        self::assertSame('', $this->render($hidden), 'asked for that language alone, the sitemap lists nothing');
        self::assertStringContainsString($this->slug($one, $shown), $this->render($shown));
    }

    public function testALanguageWhereThePageIsNoindexIsOutOfTheEntriesAndTheAlternates(): void
    {
        [$shown, $quiet] = $this->twoOtherLocales();
        [$one, $two] = $this->twoPages($shown, $quiet);
        $this->markNoindexIn($one, $quiet);
        $this->emptyTheCache();

        $xml = $this->render();

        self::assertStringNotContainsString($this->slug($one, $quiet), $xml);
        self::assertStringContainsString($this->slug($two, $quiet), $xml, 'the same language of a page not noindex stays');
        self::assertStringContainsString($this->slug($one, $shown), $xml);
    }

    /**
     * @return array{0: CmsPage, 1: CmsPage}
     */
    private function twoPages(string $first, string $second): array
    {
        $pages = [];

        foreach (['Page premiere', 'Page seconde'] as $title) {
            $page = $this->createPage($title);
            $this->publishIn($page, $first, $title.' '.$first);
            $this->publishIn($page, $second, $title.' '.$second);
            $this->titles[(int) $page->getId()] = $title;
            $pages[] = $page;
        }

        return [$pages[0], $pages[1]];
    }

    private function slug(CmsPage $page, string $locale): string
    {
        $title = (string) $this->titles[(int) $page->getId()];

        return strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $title.' '.$locale), '-'));
    }

    private function emptyTheCache(): void
    {
        $this->getService(SitemapCache::class)->invalidate();
    }

    private function render(string $lang = ''): string
    {
        return $this->getService(SitemapThemeHook::class)
            ->render('sitemap.urls', ['context' => 'content', 'lang' => $lang]);
    }
}
