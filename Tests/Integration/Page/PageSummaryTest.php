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

use TheliaCMS\ImportExport\ImportOptions;
use TheliaCMS\ImportExport\SiteDocument;
use TheliaCMS\ImportExport\SiteExporter;
use TheliaCMS\ImportExport\SiteImporter;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Model\Map\CmsPageTableMap;
use TheliaCMS\Page\Admin\PageDraft;
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

    /**
     * A theme prints both fields as they are: what an editor without the custom
     * code right types there is filtered like the content of the page.
     */
    public function testWhatTheEditorTypesIsFilteredOnSave(): void
    {
        $locale = $this->locale();
        $page = (new CmsPage())->setParent(0)->setPosition(0)->setVisible(1)->setPageType('default');
        $page->setLocale($locale)
            ->setChapo('<p>Le résumé.</p><script>alert(1)</script>')
            ->setDescription('<p>La description.</p><iframe src="https://example.com"></iframe>');

        $this->writer()->saveDraft($page, $locale, new PageDraft(title: 'Page filtrée'));

        $saved = $this->reloaded($page)->setLocale($locale);

        self::assertSame('<p>Le résumé.</p>', $saved->getChapo());
        self::assertSame('<p>La description.</p>', $saved->getDescription());
    }

    public function testWhatAnImportedFileCarriesIsFiltered(): void
    {
        $locale = $this->locale();
        $page = $this->createPage('Page importée');
        $document = $this->getService(SiteExporter::class)->exportPage($page);
        $document['pages'][0]['translations'][$locale]['chapo'] = '<p>Le résumé.</p><script>alert(1)</script>';
        $document['pages'][0]['translations'][$locale]['description'] = ['<script>alert(1)</script>'];

        $this->getService(SiteImporter::class)->import(SiteDocument::fromArray($document), new ImportOptions(replace: true));

        $imported = $this->reloaded($page)->setLocale($locale);

        self::assertSame('<p>Le résumé.</p>', $imported->getChapo());
        self::assertNull($imported->getDescription());
    }

    private function reloaded(CmsPage $page): CmsPage
    {
        CmsPageTableMap::clearInstancePool();

        $reloaded = CmsPageQuery::create()->findPk($page->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }
}
