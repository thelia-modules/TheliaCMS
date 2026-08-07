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

namespace TheliaCMS\Block;

use Propel\Runtime\ActiveQuery\Criteria;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageQuery;

/**
 * Which pages use which reusable block.
 *
 * A block reaches a page as a placeholder inside its stored HTML, so there is
 * no join to make: the contents are read once and scanned in memory. Editors
 * delete what they believe unused, and the whole point of a shared block is
 * that it is used in places its author has forgotten about.
 */
final readonly class BlockUsageFinder
{
    public function __construct(
        private BlockReference $reference,
    ) {
    }

    /**
     * Number of live pages using each block, keyed by block id.
     *
     * @param list<int> $blockIds
     *
     * @return array<int, int>
     */
    public function countsFor(array $blockIds): array
    {
        $counts = array_fill_keys($blockIds, 0);

        foreach ($this->pageContents() as $content) {
            foreach ($blockIds as $blockId) {
                if ($this->reference->isReferencedIn($content['html'], $blockId)) {
                    ++$counts[$blockId];
                }
            }
        }

        return $counts;
    }

    /**
     * Live pages using a block, with the locales they use it in.
     *
     * @return list<array{page: CmsPage, locales: list<string>}>
     */
    public function pagesUsing(int $blockId): array
    {
        $localesByPage = [];

        foreach ($this->pageContents() as $content) {
            if (!$this->reference->isReferencedIn($content['html'], $blockId)) {
                continue;
            }

            $localesByPage[$content['pageId']][] = $content['locale'];
        }

        if ([] === $localesByPage) {
            return [];
        }

        $usages = [];

        foreach (CmsPageQuery::create()->filterById(array_keys($localesByPage), Criteria::IN)->find() as $page) {
            $usages[] = ['page' => $page, 'locales' => $localesByPage[(int) $page->getId()]];
        }

        return $usages;
    }

    /**
     * Stored content of every page not in the bin, one row per locale.
     *
     * The draft counts as a use, and so does the editor project: a block
     * dropped in the canvas is in the project before it is in the exported
     * HTML, and deleting it then would break a page that is about to go live.
     *
     * @return list<array{pageId: int, locale: string, html: string}>
     */
    private function pageContents(): array
    {
        $rows = CmsPageContentQuery::create()
            ->useCmsPageQuery()
                ->filterByDeletedAt(null, Criteria::ISNULL)
            ->endUse()
            ->select(['PageId', 'Locale', 'DraftHtml', 'PublishedHtml', 'DraftProjectData'])
            ->find()
            ->toArray();

        $contents = [];

        foreach ($rows as $row) {
            $contents[] = [
                'pageId' => (int) $row['PageId'],
                'locale' => (string) $row['Locale'],
                'html' => (string) $row['DraftHtml'].(string) $row['PublishedHtml'].(string) $row['DraftProjectData'],
            ];
        }

        return $contents;
    }
}
