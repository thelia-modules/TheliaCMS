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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Model\CmsTag;
use TheliaCMS\Model\CmsTagQuery;
use TheliaCMS\Model\Map\CmsPageTableMap;

/**
 * Reads the tags of the site and the pages that carry them; PageTagWriter
 * changes them. The front office reads tagged pages through
 * PublishedPageRepository::withTag(), which only returns what is online.
 */
final readonly class PageTagRepository
{
    /**
     * Every tag, in alphabetical order.
     *
     * @return list<CmsTag>
     */
    public function all(?ConnectionInterface $connection = null): array
    {
        return iterator_to_array(CmsTagQuery::create()->orderByName()->find($connection), false);
    }

    /**
     * The names by id, for a select or a listing: one query for every row.
     *
     * @return array<int, string>
     */
    public function namesById(?ConnectionInterface $connection = null): array
    {
        $names = [];

        foreach ($this->all($connection) as $tag) {
            $names[(int) $tag->getId()] = (string) $tag->getName();
        }

        return $names;
    }

    public function find(int $id, ?ConnectionInterface $connection = null): ?CmsTag
    {
        return CmsTagQuery::create()->findPk($id, $connection);
    }

    /**
     * Compared as the column compares it, which ignores case: "Recette" finds
     * "recette".
     */
    public function findByName(string $name, ?ConnectionInterface $connection = null): ?CmsTag
    {
        $name = PageTagName::normalize($name);

        if ('' === $name) {
            return null;
        }

        return CmsTagQuery::create()->filterByName($name)->findOne($connection);
    }

    /**
     * Every page carrying the tag, drafts and binned pages included: what the
     * back office needs to say what a deletion detaches.
     *
     * @return list<CmsPage>
     */
    public function pagesOf(CmsTag $tag, ?ConnectionInterface $connection = null): array
    {
        return iterator_to_array(
            CmsPageQuery::create()->filterByTagId($tag->getId())->orderByParent()->orderByPosition()->find($connection),
            false,
        );
    }

    public function countPagesOf(CmsTag $tag): int
    {
        return CmsPageQuery::create()->filterByTagId($tag->getId())->count();
    }

    /**
     * The same count for every tag at once, the binned pages included.
     *
     * @return array<int, int>
     */
    public function pageCountsByTag(): array
    {
        $counts = [];

        $rows = CmsPageQuery::create()
            ->filterByTagId(null, Criteria::ISNOTNULL)
            ->withColumn('COUNT(*)', 'pages')
            ->groupBy(CmsPageTableMap::COL_TAG_ID)
            ->select([CmsPageTableMap::COL_TAG_ID, 'pages'])
            ->find();

        foreach ($rows as $row) {
            $counts[(int) $row[CmsPageTableMap::COL_TAG_ID]] = (int) $row['pages'];
        }

        return $counts;
    }
}
