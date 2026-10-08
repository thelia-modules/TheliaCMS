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

namespace TheliaCMS\Tests\Integration\Page;

use TheliaCMS\Page\PublishedPageRepository;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * The summary and the description of a page are not part of its body: they are
 * what a theme shows about the page elsewhere, so they have to reach the front
 * office and follow the page when it is copied.
 */
final class PageSummaryTest extends CmsIntegrationTestCase
{
    public function testThePublishedPageCarriesItsSummaryAndDescription(): void
    {
        $locale = $this->locale();
        $page = $this->createPage('Page résumée');
        $page->setLocale($locale)
            ->setChapo('<p>Le résumé.</p>')
            ->setDescription('<p>La description détaillée.</p>')
            ->save();

        $published = $this->getService(PublishedPageRepository::class)->find((int) $page->getId(), $locale);

        self::assertNotNull($published);
        self::assertSame('<p>Le résumé.</p>', $published->chapo);
        self::assertSame('<p>La description détaillée.</p>', $published->description);
    }

    public function testABlankSummaryReachesTheThemeAsNull(): void
    {
        $locale = $this->locale();
        $page = $this->createPage('Page sans résumé');
        $page->setLocale($locale)->setChapo('   ')->save();

        $published = $this->getService(PublishedPageRepository::class)->find((int) $page->getId(), $locale);

        self::assertNotNull($published);
        self::assertNull($published->chapo);
        self::assertNull($published->description);
    }

    public function testADuplicatedPageKeepsItsSummaryAndDescription(): void
    {
        $locale = $this->locale();
        $page = $this->createPage('Page à copier');
        $page->setLocale($locale)
            ->setChapo('<p>Résumé copié.</p>')
            ->setDescription('<p>Description copiée.</p>')
            ->save();

        $copy = $this->writer()->duplicate($page, $locale, '(copie)');
        $copy->setLocale($locale);

        self::assertSame('<p>Résumé copié.</p>', $copy->getChapo());
        self::assertSame('<p>Description copiée.</p>', $copy->getDescription());
    }
}
