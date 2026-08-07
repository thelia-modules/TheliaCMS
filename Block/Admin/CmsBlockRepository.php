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

namespace TheliaCMS\Block\Admin;

use Propel\Runtime\ActiveQuery\Criteria;
use TheliaCMS\Block\BlockStatus;
use TheliaCMS\Block\BlockUsageFinder;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Model\CmsBlockContent;
use TheliaCMS\Model\CmsBlockContentQuery;
use TheliaCMS\Model\CmsBlockQuery;

/**
 * Reads reusable blocks for the back office: drafts included, deleted ones
 * excluded.
 */
final readonly class CmsBlockRepository
{
    public function __construct(
        private BlockUsageFinder $usages,
    ) {
    }

    /**
     * @return list<array{block: CmsBlock, status: BlockStatus, usages: int}>
     */
    public function all(string $locale): array
    {
        $blocks = iterator_to_array($this->liveQuery()->joinWithI18n($locale, Criteria::LEFT_JOIN)->orderByCode()->find(), false);
        $counts = $this->usages->countsFor(array_map(static fn (CmsBlock $block): int => (int) $block->getId(), $blocks));

        return array_map(
            fn (CmsBlock $block): array => [
                'block' => $block,
                'status' => $this->statusOf($block, $locale),
                'usages' => $counts[(int) $block->getId()] ?? 0,
            ],
            $blocks,
        );
    }

    public function findLive(int $id): ?CmsBlock
    {
        return $this->liveQuery()->filterById($id)->findOne();
    }

    public function contentOf(CmsBlock $block, string $locale): ?CmsBlockContent
    {
        return CmsBlockContentQuery::create()
            ->filterByBlockId($block->getId())
            ->filterByLocale($locale)
            ->findOne();
    }

    public function statusOf(CmsBlock $block, string $locale): BlockStatus
    {
        return BlockStatus::resolve($this->contentOf($block, $locale));
    }

    public function codeIsTaken(string $code, ?int $exceptId = null): bool
    {
        $query = CmsBlockQuery::create()->filterByCode($code);

        if (null !== $exceptId) {
            $query->filterById($exceptId, Criteria::NOT_EQUAL);
        }

        return null !== $query->findOne();
    }

    private function liveQuery(): CmsBlockQuery
    {
        return CmsBlockQuery::create()->filterByDeletedAt(null, Criteria::ISNULL);
    }
}
