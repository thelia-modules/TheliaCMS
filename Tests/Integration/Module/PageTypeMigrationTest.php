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
 * type, and the update can be played twice.
 *
 * Runs outside a transaction: an ALTER TABLE commits on its own, so a rollback
 * would undo nothing and the test would prove nothing. The schema is put back
 * the way 1.1.0 left it, the update is played, then the table and the column
 * are restored by hand to what the shop had before the test.
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

    /**
     * @param array<int, string> $layouts the layout of some pages, by id
     */
    private function backTo110(array $layouts): void
    {
        $connection = $this->getPropelConnection();
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

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql): array
    {
        return $this->getPropelConnection()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }
}
