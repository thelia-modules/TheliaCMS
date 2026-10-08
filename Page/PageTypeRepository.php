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

namespace TheliaCMS\Page;

use Propel\Runtime\Connection\ConnectionInterface;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Model\CmsPageTypeDefinitionQuery;
use TheliaCMS\Model\Map\CmsPageTableMap;

/**
 * Reads the page types of the site; PageTypeWriter changes them. Rendering
 * never reads this table: a page carries the code of its type, and the code
 * alone picks its template.
 *
 * Every code read here is checked again: a row written by hand that is not a
 * code would otherwise reach the page form and the routes of the types screen.
 */
final readonly class PageTypeRepository
{
    /**
     * Every code, the default type first and the others in alphabetical order.
     *
     * @return list<string>
     */
    public function codes(?ConnectionInterface $connection = null): array
    {
        $codes = array_map(
            static fn (mixed $code): string => (string) $code,
            CmsPageTypeDefinitionQuery::create()->orderByCode()->select(['Code'])->find($connection)->getData(),
        );

        return array_values(array_unique([PageTypeCode::DEFAULT, ...array_filter($codes, PageTypeCode::isValid(...))]));
    }

    /**
     * Compared as a code, not as the column compares it: `cms_page_type.code`
     * ignores case, so `DEFAULT` would otherwise find `default`.
     */
    public function exists(string $code, ?ConnectionInterface $connection = null): bool
    {
        if (!PageTypeCode::isValid($code)) {
            return false;
        }

        return PageTypeCode::DEFAULT === $code
            || CmsPageTypeDefinitionQuery::create()->filterByCode($code)->exists($connection);
    }

    /**
     * Pages of this type, the binned ones included: a page restored from the
     * bin would otherwise come back with a type the site no longer has.
     */
    public function countPagesOfType(string $code): int
    {
        return CmsPageQuery::create()->filterByPageType($code)->count();
    }

    /**
     * The same count for every type at once, the binned pages included.
     *
     * @return array<string, int>
     */
    public function pageCountsByType(): array
    {
        $counts = [];

        $rows = CmsPageQuery::create()
            ->withColumn('COUNT(*)', 'pages')
            ->groupBy(CmsPageTableMap::COL_PAGE_TYPE)
            ->select([CmsPageTableMap::COL_PAGE_TYPE, 'pages'])
            ->find();

        foreach ($rows as $row) {
            $counts[(string) $row[CmsPageTableMap::COL_PAGE_TYPE]] = (int) $row['pages'];
        }

        return $counts;
    }
}
