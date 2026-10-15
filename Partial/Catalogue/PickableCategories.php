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

namespace TheliaCMS\Partial\Catalogue;

use Propel\Runtime\Propel;
use Thelia\Model\Map\CategoryTableMap;

/**
 * The categories an editor may put in a "Category" block, searched as they
 * type.
 *
 * Each is named with its parent: a catalogue often holds several "Vegetables",
 * and only the category above them tells them apart.
 */
final readonly class PickableCategories
{
    /** Enough to find a category by a few letters of its name, short enough to read. */
    public const int LIMIT = 20;

    private const string SELECT = <<<'SQL'
        SELECT c.id, i.title, parent_i18n.title AS parent_title, c.visible
        FROM category c
        LEFT JOIN category_i18n i ON i.id = c.id AND i.locale = :locale
        LEFT JOIN category_i18n parent_i18n ON parent_i18n.id = c.parent AND parent_i18n.locale = :locale
        SQL;

    /**
     * Categories online whose name in the language of the page holds the
     * text, top-level ones first.
     *
     * @return list<PickableItem>
     */
    public function search(string $text, string $locale): array
    {
        return $this->fetch(
            self::SELECT.' WHERE c.visible = 1 AND i.title LIKE :contains ORDER BY c.parent = 0 DESC, i.title, parent_i18n.title LIMIT %d',
            ['locale' => $locale, 'contains' => '%'.addcslashes($text, '%_\\').'%'],
        );
    }

    /**
     * The category a block already points at, online or not, so the editor can
     * say it went offline rather than show an empty setting.
     */
    public function find(int $id, string $locale): ?PickableItem
    {
        return $this->fetch(self::SELECT.' WHERE c.id = :id LIMIT %d', ['locale' => $locale, 'id' => $id])[0] ?? null;
    }

    /**
     * @param array<string, string|int> $parameters
     *
     * @return list<PickableItem>
     */
    private function fetch(string $sql, array $parameters): array
    {
        $statement = Propel::getReadConnection(CategoryTableMap::DATABASE_NAME)->prepare(\sprintf($sql, self::LIMIT));
        $statement->execute($parameters);

        return array_map(
            static function (array $row): PickableItem {
                $title = trim((string) $row['title']);
                $title = '' === $title ? \sprintf('#%d', (int) $row['id']) : $title;
                $parent = trim((string) $row['parent_title']);

                return new PickableItem(
                    (int) $row['id'],
                    '' === $parent ? $title : \sprintf('%s (%s)', $title, $parent),
                    1 === (int) $row['visible'],
                );
            },
            $statement->fetchAll(\PDO::FETCH_ASSOC),
        );
    }
}
