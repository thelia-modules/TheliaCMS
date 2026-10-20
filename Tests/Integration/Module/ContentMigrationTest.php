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

namespace TheliaCMS\Tests\Integration\Module;

use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\TheliaCMS;

/**
 * A site on 1.5.0 moving to documents: the columns of the former editor go,
 * the document columns come, the pages stay and their content does not, and
 * the update can be played twice.
 *
 * Runs outside a transaction: an ALTER TABLE commits on its own, so a rollback
 * would undo nothing and the test would prove nothing. The tables are put back
 * the way 1.5.0 left them, the update is played, and the shop ends up on the
 * shape it had before the test.
 */
final class ContentMigrationTest extends CmsIntegrationTestCase
{
    protected bool $useTransaction = false;

    protected function tearDown(): void
    {
        // Whatever the test did, leave the tables in the 2.0.0 shape: playing
        // the update once more is exactly what does that. The template the
        // test inserted is removed here too, not left to the code under test.
        (new TheliaCMS())->update('1.5.0', '2.0.0', $this->getPropelConnection());
        $this->getPropelConnection()->exec("DELETE FROM cms_page_template WHERE code LIKE 'test-%'");

        parent::tearDown();
    }

    public function testThePagesStayTheirContentGoesAndTheUpdateCanBePlayedTwice(): void
    {
        $page = $this->createPage('Page écrite avec l’ancien éditeur', html: '<h1>Avant</h1><p>Un texte de 1.5.0.</p>');
        $pageId = (int) $page->getId();
        $locale = $this->locale();

        $this->backTo150($pageId, $locale);

        self::assertTrue($this->columnExists('cms_page_content', 'draft_project_data'));
        self::assertFalse($this->columnExists('cms_page_content', 'draft_document'));
        self::assertSame(1, $this->rowCount('cms_page_template'));

        $module = new TheliaCMS();
        $module->update('1.5.0', '2.0.0', $this->getPropelConnection());
        $module->update('1.5.0', '2.0.0', $this->getPropelConnection());

        foreach (['cms_page_content', 'cms_block_content'] as $table) {
            self::assertTrue($this->columnExists($table, 'draft_document'), $table);
            self::assertTrue($this->columnExists($table, 'published_document'), $table);
            self::assertFalse($this->columnExists($table, 'draft_project_data'), $table);
            self::assertFalse($this->columnExists($table, 'draft_css'), $table);
            self::assertFalse($this->columnExists($table, 'published_css'), $table);
        }

        self::assertTrue($this->columnExists('cms_page_revision', 'document'));
        self::assertFalse($this->columnExists('cms_page_revision', 'project_data'));
        self::assertFalse($this->columnExists('cms_page_revision', 'css'));

        $row = $this->rows(\sprintf("SELECT draft_html, published_html, published_at, draft_document FROM cms_page_content WHERE page_id = %d AND locale = '%s'", $pageId, $locale));

        self::assertCount(1, $row, 'The content row stays, emptied: the page keeps its place in every language it had.');
        self::assertNull($row[0]['draft_html']);
        self::assertNull($row[0]['published_html']);
        self::assertNull($row[0]['published_at']);
        self::assertNull($row[0]['draft_document']);

        $title = $this->rows(\sprintf("SELECT title FROM cms_page_i18n WHERE id = %d AND locale = '%s'", $pageId, $locale));
        self::assertSame('Page écrite avec l’ancien éditeur', $title[0]['title'] ?? null, 'The page itself is kept.');

        self::assertSame(0, $this->rowCount(\sprintf('cms_page_revision WHERE page_id = %d', $pageId)));
        self::assertSame(0, $this->rowCount(\sprintf('cms_page_search WHERE page_id = %d', $pageId)));
        self::assertSame(0, $this->rowCount('cms_page_template'), 'A template saved by 1.x holds markup no document can be read from.');
    }

    public function testAFreshInstallIsLeftAlone(): void
    {
        $page = $this->createPage('Page écrite en 2.0.0', html: '<h1>Après</h1><p>Un texte de 2.0.0.</p>');
        $pageId = (int) $page->getId();

        // The shape is already the one of 2.0.0: nothing is purged.
        (new TheliaCMS())->update('1.5.0', '2.0.0', $this->getPropelConnection());

        $row = $this->rows(\sprintf('SELECT published_html, published_document FROM cms_page_content WHERE page_id = %d', $pageId));

        self::assertStringContainsString('Un texte de 2.0.0.', (string) $row[0]['published_html']);
        self::assertNotNull($row[0]['published_document']);
    }

    private function backTo150(int $pageId, string $locale): void
    {
        $connection = $this->getPropelConnection();

        foreach (['cms_page_content', 'cms_block_content'] as $table) {
            $connection->exec(\sprintf('ALTER TABLE %s DROP COLUMN draft_document, DROP COLUMN published_document, ADD COLUMN draft_project_data LONGTEXT AFTER locale, ADD COLUMN draft_css LONGTEXT AFTER draft_html, ADD COLUMN published_css LONGTEXT AFTER published_html', $table));
        }

        $connection->exec('ALTER TABLE cms_page_revision DROP COLUMN document, ADD COLUMN project_data LONGTEXT AFTER locale, ADD COLUMN css LONGTEXT AFTER html');

        $connection->prepare('UPDATE cms_page_content SET draft_project_data = ?, draft_css = ?, published_css = ? WHERE page_id = ? AND locale = ?')
            ->execute(['{"pages":[{"frames":[]}]}', 'body{color:red}', '.cms-page-content{color:red}', $pageId, $locale]);

        $connection->prepare('INSERT INTO cms_page_template (code, title, payload, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())')
            ->execute(['test-'.bin2hex(random_bytes(4)), 'Modèle de 1.5.0', '{"format":"thelia-cms","version":1,"pages":[]}']);
    }

    private function columnExists(string $table, string $column): bool
    {
        return [] !== $this->rows(\sprintf("SHOW COLUMNS FROM %s LIKE '%s'", $table, $column));
    }

    private function rowCount(string $from): int
    {
        return (int) $this->rows('SELECT COUNT(*) AS n FROM '.$from)[0]['n'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql): array
    {
        return $this->getPropelConnection()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }
}
