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

namespace TheliaCMS\Page\Admin;

use Propel\Runtime\ActiveQuery\Criteria;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageQuery;

/**
 * Reads the page tree for the back office. Kept apart from the front-office
 * repository on purpose: this one sees drafts and deleted pages, the other one
 * must never be able to.
 */
final readonly class CmsPageAdminRepository
{
    /**
     * Depth-first walk of the live tree, each row carrying its depth so the
     * listing can indent without a second pass.
     *
     * @return list<array{page: CmsPage, depth: int, status: PageStatus}>
     */
    public function tree(string $locale): array
    {
        $byParent = [];

        foreach ($this->livePages($locale) as $page) {
            $byParent[(int) $page->getParent()][] = $page;
        }

        return $this->flatten($byParent, 0, 0, $locale);
    }

    /**
     * @return list<CmsPage>
     */
    public function trash(string $locale): array
    {
        return iterator_to_array(
            CmsPageQuery::create()
                ->filterByDeletedAt(null, Criteria::ISNOTNULL)
                ->joinWithI18n($locale, Criteria::LEFT_JOIN)
                ->orderByDeletedAt(Criteria::DESC)
                ->find(),
            false
        );
    }

    public function findLive(int $id): ?CmsPage
    {
        return CmsPageQuery::create()
            ->filterById($id)
            ->filterByDeletedAt(null, Criteria::ISNULL)
            ->findOne();
    }

    public function findDeleted(int $id): ?CmsPage
    {
        return CmsPageQuery::create()
            ->filterById($id)
            ->filterByDeletedAt(null, Criteria::ISNOTNULL)
            ->findOne();
    }

    /**
     * Pages that could become the parent of `$pageId`, i.e. everything but the
     * page itself and its own descendants — otherwise the tree gets a cycle
     * and every walk of it hangs.
     *
     * @return array<string, int>
     */
    public function parentChoices(string $locale, ?int $pageId = null): array
    {
        $excluded = null === $pageId ? [] : $this->descendantIds($pageId) + [$pageId => $pageId];
        $choices = [];

        foreach ($this->tree($locale) as $row) {
            $id = (int) $row['page']->getId();

            if (isset($excluded[$id])) {
                continue;
            }

            $choices[str_repeat('— ', $row['depth']).$this->label($row['page'], $locale)] = $id;
        }

        return $choices;
    }

    public function statusOf(CmsPage $page, string $locale): PageStatus
    {
        $content = CmsPageContentQuery::create()
            ->filterByPageId($page->getId())
            ->filterByLocale($locale)
            ->findOne();

        return PageStatus::resolve($page, $content);
    }

    /**
     * @return list<CmsPage>
     */
    private function livePages(string $locale): array
    {
        return iterator_to_array(
            CmsPageQuery::create()
                ->filterByDeletedAt(null, Criteria::ISNULL)
                ->joinWithI18n($locale, Criteria::LEFT_JOIN)
                ->orderByParent()
                ->orderByPosition()
                ->find(),
            false
        );
    }

    /**
     * @param array<int, list<CmsPage>> $byParent
     *
     * @return list<array{page: CmsPage, depth: int, status: PageStatus}>
     */
    private function flatten(array $byParent, int $parentId, int $depth, string $locale): array
    {
        $rows = [];

        foreach ($byParent[$parentId] ?? [] as $page) {
            $rows[] = ['page' => $page, 'depth' => $depth, 'status' => $this->statusOf($page, $locale)];
            $rows = array_merge($rows, $this->flatten($byParent, (int) $page->getId(), $depth + 1, $locale));
        }

        return $rows;
    }

    /**
     * @return array<int, int>
     */
    private function descendantIds(int $pageId): array
    {
        $ids = [];
        $frontier = [$pageId];

        while ([] !== $frontier) {
            $children = CmsPageQuery::create()
                ->filterByParent($frontier)
                ->select(['Id'])
                ->find()
                ->toArray();

            $frontier = [];

            foreach ($children as $childId) {
                $childId = (int) $childId;

                if (isset($ids[$childId])) {
                    continue;
                }

                $ids[$childId] = $childId;
                $frontier[] = $childId;
            }
        }

        return $ids;
    }

    private function label(CmsPage $page, string $locale): string
    {
        $title = trim((string) $page->setLocale($locale)->getTitle());

        return '' === $title ? '#'.$page->getId() : $title;
    }
}
