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

namespace TheliaCMS\Tests\Integration\ImportExport;

use Propel\Runtime\Propel;
use TheliaCMS\ImportExport\ImportOptions;
use TheliaCMS\ImportExport\SiteDocument;
use TheliaCMS\ImportExport\SiteExporter;
use TheliaCMS\ImportExport\SiteImporter;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * The export file is the backup of a site. What it has to hold is not "most of
 * the content" but all of it: emptying the tables and importing the file back
 * has to give the same site.
 *
 * This is the test that used to be a manual procedure.
 */
final class SiteRoundTripTest extends CmsIntegrationTestCase
{
    public function testEmptyingTheSiteAndImportingItBackRestoresTheContent(): void
    {
        $parent = $this->createPage('Nos services', html: '<h1>Nos services</h1><p>Ce que nous faisons.</p>');
        $this->createPage(
            'Conseil et accompagnement',
            parent: (int) $parent->getId(),
            html: '<h1>Conseil</h1><p>Un texte reconnaissable, 42.</p>',
        );
        $this->createPage('Brouillon en cours', published: false);

        $before = $this->snapshot();

        self::assertArrayHasKey('nos-services', $before);
        self::assertArrayHasKey('nos-services/conseil-et-accompagnement', $before);
        self::assertStringContainsString(
            'reconnaissable, 42',
            (string) $before['nos-services/conseil-et-accompagnement']['published_html'],
        );

        $document = $this->getService(SiteExporter::class)->export();

        $this->emptyTheSite();
        self::assertSame([], $this->snapshot(), 'The site was not emptied, so the import proves nothing.');

        $report = $this->getService(SiteImporter::class)
            ->import(SiteDocument::fromArray($document), new ImportOptions());

        self::assertSame([], $report->warnings());
        self::assertSame($before, $this->snapshot());
    }

    public function testASecondImportChangesNothing(): void
    {
        $this->createPage('Page idempotente', html: '<h1>Page idempotente</h1>');

        $document = SiteDocument::fromArray($this->getService(SiteExporter::class)->export());
        $importer = $this->getService(SiteImporter::class);

        $importer->import($document, new ImportOptions());
        $afterFirst = $this->snapshot();

        $report = $importer->import($document, new ImportOptions());

        self::assertSame($afterFirst, $this->snapshot(), 'Importing the same file twice duplicated content.');
        self::assertSame([], $report->createdCounts(), 'Nothing was new, so nothing should have been created.');
    }

    /**
     * A page holding an emoji survives being exported and imported back.
     *
     * The importer writes the content rows itself, without going through the
     * editor, which is exactly why the character is spelled out on the Propel
     * save hook and not in the writers: this is the path that would otherwise
     * hand the four-byte character straight to a statement that refuses it.
     */
    public function testAPageHoldingAnEmojiComesBackFromTheExportUnchanged(): void
    {
        $this->createPage('Page expressive', html: "<h1>Page expressive</h1><p>Photo \u{1F4F7} du studio</p>");

        $before = $this->snapshot();

        self::assertStringContainsString('&#128247;', (string) $before['page-expressive']['published_html']);

        $document = SiteDocument::fromArray($this->getService(SiteExporter::class)->export());
        $this->emptyTheSite();
        $report = $this->getService(SiteImporter::class)->import($document, new ImportOptions());

        self::assertSame([], $report->warnings());
        self::assertSame($before, $this->snapshot(), 'The emoji did not come back the way it went in.');
    }

    public function testAnImportedPageIsNotFlaggedAsChangedSinceItsPublication(): void
    {
        $this->createPage('Page publiée', html: '<h1>Page publiée</h1>');

        $document = SiteDocument::fromArray($this->getService(SiteExporter::class)->export());
        $this->emptyTheSite();
        $this->getService(SiteImporter::class)->import($document, new ImportOptions());

        $rows = $this->query(
            "SELECT c.updated_at, c.published_at
             FROM cms_page_content c
             INNER JOIN cms_page_i18n i ON i.id = c.page_id AND i.locale = c.locale
             WHERE i.title = 'Page publiée'",
        );

        self::assertCount(1, $rows);
        self::assertNotNull($rows[0]['published_at']);
        self::assertLessThanOrEqual(
            $rows[0]['published_at'],
            $rows[0]['updated_at'],
            'The restored page says it holds unpublished work, so the back office asks to publish it again.',
        );
    }

    /**
     * Everything a visitor would see, keyed by address rather than by id: the
     * ids of an imported site are new, and comparing them would only prove that
     * auto-increment works.
     *
     * @return array<string, array<string, string|null>>
     */
    private function snapshot(): array
    {
        $rows = $this->query(
            "SELECT u.url,
                    i.title,
                    i.locale,
                    c.published_html,
                    c.published_at,
                    parent_i18n.title AS parent_title
             FROM rewriting_url u
             INNER JOIN cms_page p ON p.id = u.view_id AND p.deleted_at IS NULL
             INNER JOIN cms_page_i18n i ON i.id = p.id AND i.locale = u.view_locale
             LEFT JOIN cms_page_content c ON c.page_id = p.id AND c.locale = u.view_locale
             LEFT JOIN cms_page_i18n parent_i18n ON parent_i18n.id = p.parent AND parent_i18n.locale = u.view_locale
             WHERE u.view = 'cmspage' AND u.redirected IS NULL
             ORDER BY u.url",
        );

        $snapshot = [];

        foreach ($rows as $row) {
            $url = (string) $row['url'];
            unset($row['url']);
            $snapshot[$url] = $row;
        }

        return $snapshot;
    }

    /**
     * DELETE rather than TRUNCATE: the transaction of the test case has to be
     * able to roll this back, and TRUNCATE commits on MySQL.
     */
    private function emptyTheSite(): void
    {
        $connection = $this->getPropelConnection();

        foreach ([
            'cms_page_search',
            'cms_page_revision',
            'cms_page_content',
            'cms_page_i18n',
            'cms_page',
        ] as $table) {
            $connection->exec('DELETE FROM '.$table);
        }

        $connection->exec("DELETE FROM rewriting_url WHERE view = 'cmspage'");
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function query(string $sql): array
    {
        $statement = Propel::getConnection('TheliaMain')->prepare($sql);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }
}
