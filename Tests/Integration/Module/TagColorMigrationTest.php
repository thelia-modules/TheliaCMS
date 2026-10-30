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
 * A site on 2.4.0 that has tags moving to colors: the column appears, the
 * tags keep their name and have no color, and the update can be played twice.
 *
 * Runs outside a transaction, as PageTagMigrationTest does and for the same
 * reasons, the column dropped with ALGORITHM=COPY included.
 */
final class TagColorMigrationTest extends CmsIntegrationTestCase
{
    protected bool $useTransaction = false;

    private ?int $tagId = null;

    protected function tearDown(): void
    {
        $connection = $this->getPropelConnection();

        (new TheliaCMS())->update('2.4.0', '2.5.0', $connection);

        if (null !== $this->tagId) {
            $connection->exec(\sprintf('DELETE FROM cms_tag WHERE id = %d', $this->tagId));
        }

        parent::tearDown();
    }

    public function testTheColumnAppearsEmptyOnTheTagsOfTheSiteAndTheUpdateCanBePlayedTwice(): void
    {
        $connection = $this->getPropelConnection();
        $connection->exec('ALTER TABLE cms_tag DROP COLUMN color, ALGORITHM=COPY');
        $connection->exec("INSERT INTO cms_tag (name, created_at, updated_at) VALUES ('Étiquette d’avant les couleurs', NOW(), NOW())");
        $this->tagId = (int) $connection->lastInsertId();

        $module = new TheliaCMS();
        $module->update('2.4.0', '2.5.0', $connection);
        $module->update('2.4.0', '2.5.0', $connection);

        $column = $this->rows("SELECT IS_NULLABLE AS is_nullable, CHARACTER_MAXIMUM_LENGTH AS size FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'cms_tag' AND column_name = 'color'");
        self::assertSame('YES', $column[0]['is_nullable'] ?? null);
        self::assertSame(16, (int) $column[0]['size']);

        $tag = $this->rows(\sprintf('SELECT name, color FROM cms_tag WHERE id = %d', $this->tagId))[0];
        self::assertSame('Étiquette d’avant les couleurs', $tag['name']);
        self::assertNull($tag['color']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql): array
    {
        return $this->getPropelConnection()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }
}
