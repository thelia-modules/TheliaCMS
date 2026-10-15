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
use Thelia\Model\Map\ProductTableMap;

/**
 * The products an editor may put in a "Product" block, searched as they type.
 *
 * Plain SQL rather than a Propel query: one round trip matching the
 * reference, the name in the language of the page and the barcode of any sale
 * element, which Propel would split over joins and a sub-query for no gain.
 */
final readonly class PickableProducts
{
    /** Enough to find a product by a few letters of its name, short enough to read. */
    public const int LIMIT = 20;

    private const string SELECT = <<<'SQL'
        SELECT p.id, p.ref, i.title, p.visible
        FROM product p
        LEFT JOIN product_i18n i ON i.id = p.id AND i.locale = :locale
        SQL;

    /**
     * Products online whose reference starts with the text, whose name holds
     * it, or one of whose sale elements carries it as barcode. An exact
     * reference comes first, then by name.
     *
     * @return list<PickableItem>
     */
    public function search(string $text, string $locale): array
    {
        $escaped = addcslashes($text, '%_\\');

        return $this->fetch(
            self::SELECT.<<<'SQL'
                 WHERE p.visible = 1
                  AND (
                    p.ref LIKE :prefix
                    OR i.title LIKE :contains
                    OR EXISTS (SELECT 1 FROM product_sale_elements pse WHERE pse.product_id = p.id AND pse.ean_code = :text)
                  )
                ORDER BY p.ref = :text DESC, i.title, p.ref
                LIMIT %d
                SQL,
            ['locale' => $locale, 'prefix' => $escaped.'%', 'contains' => '%'.$escaped.'%', 'text' => $text],
        );
    }

    /**
     * The product a block already points at, online or not, so the editor can
     * say it went offline rather than show an empty setting.
     */
    public function find(int $id, string $locale): ?PickableItem
    {
        return $this->fetch(self::SELECT.' WHERE p.id = :id LIMIT %d', ['locale' => $locale, 'id' => $id])[0] ?? null;
    }

    /**
     * @param array<string, string|int> $parameters
     *
     * @return list<PickableItem>
     */
    private function fetch(string $sql, array $parameters): array
    {
        $statement = Propel::getReadConnection(ProductTableMap::DATABASE_NAME)->prepare(\sprintf($sql, self::LIMIT));
        $statement->execute($parameters);

        return array_map(
            static function (array $row): PickableItem {
                $title = trim((string) $row['title']);
                $ref = (string) $row['ref'];

                return new PickableItem(
                    (int) $row['id'],
                    '' === $title ? $ref : \sprintf('%s (%s)', $title, $ref),
                    1 === (int) $row['visible'],
                );
            },
            $statement->fetchAll(\PDO::FETCH_ASSOC),
        );
    }
}
