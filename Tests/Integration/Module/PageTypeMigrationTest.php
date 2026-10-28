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
 * A site on 1.1.0 moving to page types: the `layout` of each page becomes its
 * type, and the update can be played twice. Then a site on 2.1.0 moving to
 * the instructions of a type, replayable as well, and a site already on 2.2.0,
 * whose update to 2.2.0 did not carry them yet.
 *
 * Runs outside a transaction: an ALTER TABLE commits on its own, so a rollback
 * would undo nothing and the test would prove nothing. The schema is put back
 * the way 1.1.0 left it, the update is played, then the table and the column
 * are restored by hand to what the shop had before the test.
 *
 * A column is dropped with ALGORITHM=COPY: MariaDB drops it instantly
 * otherwise and keeps it in the rows, so about thirty runs leave the table
 * refusing the column back ("Row size too large").
 */
final class PageTypeMigrationTest extends CmsIntegrationTestCase
{
    protected bool $useTransaction = false;

    /** @var array<int, string> */
    private array $typesBefore = [];

    /** @var list<string> */
    private array $codesBefore = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->typesBefore = array_column($this->rows('SELECT id, page_type FROM cms_page'), 'page_type', 'id');
        $this->codesBefore = array_column($this->rows('SELECT code FROM cms_page_type'), 'code');
    }

    protected function tearDown(): void
    {
        $connection = $this->getPropelConnection();

        if ($this->columnExists('layout')) {
            $connection->exec("ALTER TABLE cms_page CHANGE COLUMN layout page_type VARCHAR(50) NOT NULL DEFAULT 'default'");
        }

        $connection->exec('CREATE TABLE IF NOT EXISTS cms_page_type (id INTEGER NOT NULL AUTO_INCREMENT, code VARCHAR(50) NOT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, PRIMARY KEY (id), UNIQUE INDEX unq_cms_page_type_code (code)) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC');
        // The shape 2.0.0 gives the table, which the update to 1.2.0 knows
        // nothing of.
        if ([] === $this->rows("SHOW COLUMNS FROM cms_page_type LIKE 'brief'")) {
            $connection->exec('ALTER TABLE cms_page_type ADD COLUMN brief LONGTEXT AFTER code, ADD COLUMN brief_tone VARCHAR(200) AFTER brief, ADD COLUMN brief_audience VARCHAR(200) AFTER brief_tone, ADD COLUMN fields LONGTEXT AFTER brief_audience');
        }

        // The shape 2.2.1 gives it.
        if (!$this->typeColumnExists('instructions')) {
            $connection->exec('ALTER TABLE cms_page_type ADD COLUMN instructions LONGTEXT DEFAULT NULL AFTER brief_audience');
        }

        // The shape 2.4.0 gives the contents, should its test stop half way.
        foreach (['cms_page_content', 'cms_item_content'] as $table) {
            if ([] === $this->rows(\sprintf("SHOW COLUMNS FROM %s LIKE 'translated_from'", $table))) {
                $connection->exec(\sprintf('ALTER TABLE %s ADD COLUMN translated_from VARCHAR(5) DEFAULT NULL AFTER draft_updated_by', $table));
            }

            if ([] === $this->rows(\sprintf("SHOW COLUMNS FROM %s LIKE 'source_fingerprint'", $table))) {
                $connection->exec(\sprintf('ALTER TABLE %s ADD COLUMN source_fingerprint CHAR(64) DEFAULT NULL AFTER translated_from', $table));
            }
        }

        $connection->exec("CREATE TABLE IF NOT EXISTS cms_page_type_i18n (id INTEGER NOT NULL, locale VARCHAR(5) DEFAULT 'en_US' NOT NULL, title VARCHAR(255), description TEXT, PRIMARY KEY (id, locale), CONSTRAINT cms_page_type_i18n_fk_7b1d2a FOREIGN KEY (id) REFERENCES cms_page_type (id) ON DELETE CASCADE) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC");
        $connection->exec('DELETE FROM cms_page_type');

        foreach ($this->codesBefore as $code) {
            $connection->prepare('INSERT INTO cms_page_type (code, created_at, updated_at) VALUES (?, NOW(), NOW())')->execute([$code]);
        }

        foreach ($this->typesBefore as $id => $type) {
            $connection->prepare('UPDATE cms_page SET page_type = ? WHERE id = ?')->execute([$type, $id]);
        }

        parent::tearDown();
    }

    public function testTheLayoutOfEachPageBecomesItsTypeAndTheUpdateCanBePlayedTwice(): void
    {
        $landing = (int) $this->createPage('Page d’accueil de campagne')->getId();
        $custom = (int) $this->createPage('Page à mise en page maison')->getId();
        $broken = (int) $this->createPage('Page modifiée à la main')->getId();
        $lineFeed = (int) $this->createPage('Page à saut de ligne')->getId();
        $carriageReturn = (int) $this->createPage('Page à retour chariot')->getId();
        $doubleHyphen = (int) $this->createPage('Page à double tiret')->getId();

        $this->backTo110([
            $landing => 'landing',
            $custom => 'recette',
            $broken => 'Recipe',
            $lineFeed => "recette\n",
            $carriageReturn => "recette\r",
            $doubleHyphen => 'a--b',
        ]);

        $module = new TheliaCMS();
        $module->update('1.1.0', '1.2.0', $this->getPropelConnection());
        $module->update('1.1.0', '1.2.0', $this->getPropelConnection());

        self::assertFalse($this->columnExists('layout'));
        self::assertSame('landing', $this->typeOf($landing));
        self::assertSame('recette', $this->typeOf($custom), 'A layout a site had added by hand is kept as a type.');
        self::assertSame('default', $this->typeOf($broken), 'A value that is not a code would end up in a template name.');
        // `$` of a regular expression also matches before a trailing line
        // break: these are the values an anchored pattern lets through.
        self::assertSame('default', $this->typeOf($lineFeed));
        self::assertSame('default', $this->typeOf($carriageReturn));
        self::assertSame('default', $this->typeOf($doubleHyphen));
        self::assertSame('varchar(50)', strtolower((string) $this->rows("SHOW COLUMNS FROM cms_page LIKE 'page_type'")[0]['Type']));

        $codes = array_column($this->rows('SELECT code FROM cms_page_type ORDER BY code'), 'code');
        self::assertSame(['default', 'full-width', 'landing', 'recette'], $codes, 'Each type exists once, the former layouts included.');
    }

    public function testTheUpdateTo221GivesTheTypesTheirInstructionsAndCanBePlayedTwice(): void
    {
        $this->withoutInstructions();

        $module = new TheliaCMS();
        $module->update('2.1.0', '2.2.1', $this->getPropelConnection());
        $module->update('2.1.0', '2.2.1', $this->getPropelConnection());

        $this->assertTheTypesHaveTheirInstructions();
    }

    public function testASiteAlreadyOn220GetsTheInstructionsOfItsTypesAndTheUpdateCanBePlayedTwice(): void
    {
        // A site that reached 2.2.0 before the column moved to 2.2.1 has none,
        // and the update from 2.2.0 is the only one it plays.
        $this->withoutInstructions();

        $module = new TheliaCMS();
        $module->update('2.2.0', '2.2.1', $this->getPropelConnection());
        $module->update('2.2.0', '2.2.1', $this->getPropelConnection());

        $this->assertTheTypesHaveTheirInstructions();
    }

    public function testTheUpdateTo231GivesTheRevisionsOfItemsTheirLabelAndCanBePlayedTwice(): void
    {
        // A test database prepared before 2.3.1 has no column to drop.
        if ([] !== $this->rows("SHOW COLUMNS FROM cms_item_revision LIKE 'label'")) {
            $this->getPropelConnection()->exec('ALTER TABLE cms_item_revision DROP COLUMN label, ALGORITHM=COPY');
        }

        self::assertSame([], $this->rows("SHOW COLUMNS FROM cms_item_revision LIKE 'label'"));

        $module = new TheliaCMS();
        $module->update('2.3.0', '2.3.1', $this->getPropelConnection());
        $module->update('2.3.0', '2.3.1', $this->getPropelConnection());

        $column = $this->rows("SHOW COLUMNS FROM cms_item_revision LIKE 'label'");
        self::assertCount(1, $column);
        self::assertSame('varchar(60)', strtolower((string) $column[0]['Type']));
        self::assertSame('YES', $column[0]['Null']);
    }

    public function testTheUpdateTo240GivesTheContentsTheSourceOfTheirTranslationAndCanBePlayedTwice(): void
    {
        foreach (['cms_page_content', 'cms_item_content'] as $table) {
            // A test database prepared before 2.4.0 has no column to drop.
            foreach (['source_fingerprint', 'translated_from'] as $column) {
                if ([] !== $this->rows(\sprintf("SHOW COLUMNS FROM %s LIKE '%s'", $table, $column))) {
                    $this->getPropelConnection()->exec(\sprintf('ALTER TABLE %s DROP COLUMN %s, ALGORITHM=COPY', $table, $column));
                }
            }

            self::assertSame([], $this->rows(\sprintf("SHOW COLUMNS FROM %s WHERE Field IN ('translated_from', 'source_fingerprint')", $table)));
        }

        $module = new TheliaCMS();
        $module->update('2.3.1', '2.4.0', $this->getPropelConnection());
        $module->update('2.3.1', '2.4.0', $this->getPropelConnection());

        foreach (['cms_page_content', 'cms_item_content'] as $table) {
            $columns = $this->rows(\sprintf("SHOW COLUMNS FROM %s WHERE Field IN ('translated_from', 'source_fingerprint')", $table));
            self::assertSame(
                ['translated_from' => 'varchar(5) YES', 'source_fingerprint' => 'char(64) YES'],
                array_column(array_map(static fn (array $column): array => ['field' => $column['Field'], 'shape' => strtolower((string) $column['Type']).' '.$column['Null']], $columns), 'shape', 'field'),
                $table,
            );
        }
    }

    private function withoutInstructions(): void
    {
        $this->getPropelConnection()->exec('ALTER TABLE cms_page_type DROP COLUMN instructions, ALGORITHM=COPY');
        self::assertFalse($this->typeColumnExists('instructions'));
    }

    private function assertTheTypesHaveTheirInstructions(): void
    {
        $column = $this->rows("SHOW COLUMNS FROM cms_page_type LIKE 'instructions'");
        self::assertCount(1, $column);
        self::assertSame('longtext', strtolower((string) $column[0]['Type']));
        self::assertSame('YES', $column[0]['Null']);
    }

    /**
     * @param array<int, string> $layouts the layout of some pages, by id
     */
    private function backTo110(array $layouts): void
    {
        $connection = $this->getPropelConnection();
        // A 1.1.0 site has neither the types nor their translations.
        $connection->exec('DROP TABLE IF EXISTS cms_page_type_i18n');
        $connection->exec('DROP TABLE cms_page_type');
        $connection->exec("ALTER TABLE cms_page CHANGE COLUMN page_type layout VARCHAR(20) NOT NULL DEFAULT 'default'");

        foreach ($layouts as $id => $layout) {
            $connection->prepare('UPDATE cms_page SET layout = ? WHERE id = ?')->execute([$layout, $id]);
        }
    }

    private function typeOf(int $pageId): string
    {
        return (string) $this->rows(\sprintf('SELECT page_type FROM cms_page WHERE id = %d', $pageId))[0]['page_type'];
    }

    private function columnExists(string $column): bool
    {
        return [] !== $this->rows(\sprintf("SHOW COLUMNS FROM cms_page LIKE '%s'", $column));
    }

    private function typeColumnExists(string $column): bool
    {
        return [] !== $this->rows(\sprintf("SHOW COLUMNS FROM cms_page_type LIKE '%s'", $column));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql): array
    {
        return $this->getPropelConnection()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }
}
