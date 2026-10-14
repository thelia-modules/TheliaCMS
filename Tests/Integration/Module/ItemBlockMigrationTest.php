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
 * A site on 1.3.0 moving to item blocks: the link table appears with its two
 * unique keys and its cascade, and the update can be played twice.
 *
 * Runs outside a transaction, like the other migration tests: a DDL statement
 * commits on its own. The rows of the table are put back afterwards.
 */
final class ItemBlockMigrationTest extends CmsIntegrationTestCase
{
    protected bool $useTransaction = false;

    /** @var list<array<string, mixed>> */
    private array $linksBefore = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->linksBefore = $this->rows('SELECT item_type, item_id, block_id FROM cms_item_block');
    }

    protected function tearDown(): void
    {
        $connection = $this->getPropelConnection();

        (new TheliaCMS())->update('1.3.0', '1.4.0', $connection);

        foreach ($this->linksBefore as $link) {
            $connection->prepare('INSERT IGNORE INTO cms_item_block (item_type, item_id, block_id, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())')
                ->execute([$link['item_type'], $link['item_id'], $link['block_id']]);
        }

        parent::tearDown();
    }

    public function testTheTableAppearsAndTheUpdateCanBePlayedTwice(): void
    {
        $this->backTo130();

        $module = new TheliaCMS();
        $module->update('1.3.0', '1.4.0', $this->getPropelConnection());
        $module->update('1.3.0', '1.4.0', $this->getPropelConnection());

        self::assertSame([], $this->rows('SELECT id FROM cms_item_block'));

        $uniques = array_column($this->rows("SELECT INDEX_NAME AS index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'cms_item_block' AND NON_UNIQUE = 0 GROUP BY INDEX_NAME"), 'index_name');
        sort($uniques);
        self::assertSame(['PRIMARY', 'unq_cms_item_block_block', 'unq_cms_item_block_item'], $uniques, 'One block per item, one item per block.');

        $constraints = $this->rows("SELECT DELETE_RULE AS delete_rule FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = 'cms_item_block' AND constraint_name = 'fk_cms_item_block_block'");
        self::assertSame('CASCADE', $constraints[0]['delete_rule'] ?? null, 'The link goes with its block.');
    }

    private function backTo130(): void
    {
        $this->getPropelConnection()->exec('DROP TABLE IF EXISTS cms_item_block');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql): array
    {
        return $this->getPropelConnection()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }
}
