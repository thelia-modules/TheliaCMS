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

use TheliaCMS\Sitemap\SitemapThemeHook;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * What the module adds to the sitemap of the theme. The rules it has to hold —
 * only reachable pages, `lastmod` from the publication date — are decisions
 * about what a crawler is told, and they are only observable against real rows.
 */
final class SitemapThemeHookTest extends CmsIntegrationTestCase
{
    public function testAPublishedPageIsListedWithItsPublicationDate(): void
    {
        $page = $this->createPage('Atelier de gravure');
        $publishedAt = $this->publishedAt((int) $page->getId());

        $xml = $this->render();

        self::assertStringContainsString('<loc>', $xml);
        self::assertStringContainsString('atelier-de-gravure', $xml);
        self::assertStringContainsString('<lastmod>'.$publishedAt->format('c').'</lastmod>', $xml);
    }

    public function testADraftIsNotListed(): void
    {
        $this->createPage('Page en préparation', published: false);

        self::assertStringNotContainsString('page-en-preparation', $this->render());
    }

    public function testAPageMarkedNoindexIsNotListed(): void
    {
        $page = $this->createPage('Page discrète');
        $page->setLocale($this->locale())->setNoindex(1)->save();

        self::assertStringNotContainsString('page-discrete', $this->render());
    }

    public function testAHiddenPageIsNotListed(): void
    {
        $page = $this->createPage('Page hors ligne');
        $page->setVisible(0)->save();

        self::assertStringNotContainsString('page-hors-ligne', $this->render());
    }

    public function testTheHookStaysOutOfTheCatalogueSections(): void
    {
        $this->createPage('Atelier de moulage');

        self::assertSame('', $this->render(context: 'product'));
    }

    private function render(string $context = 'content'): string
    {
        return $this->getService(SitemapThemeHook::class)
            ->render('sitemap.urls', ['context' => $context, 'lang' => $this->locale()]);
    }

    private function publishedAt(int $pageId): \DateTimeInterface
    {
        $content = \TheliaCMS\Model\CmsPageContentQuery::create()
            ->filterByPageId($pageId)
            ->filterByLocale($this->locale())
            ->findOne();

        self::assertNotNull($content);
        $publishedAt = $content->getPublishedAt();
        self::assertNotNull($publishedAt);

        return $publishedAt;
    }
}
