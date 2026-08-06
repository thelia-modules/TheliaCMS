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

namespace TheliaCMS\Media\Admin;

use Propel\Runtime\ActiveQuery\Criteria;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageQuery;

/**
 * Which pages use which image.
 *
 * Editors delete images they believe unused, and a deleted image leaves broken
 * pictures behind. There is no join to make: an image reaches a page as a URL
 * inside its stored HTML, so the contents are read once and scanned in memory
 * for every image at a time — one query for a whole grid rather than one per
 * card.
 */
final readonly class MediaUsageFinder
{
    /**
     * Number of live pages using each image, keyed by image id.
     *
     * @param list<int> $imageIds
     *
     * @return array<int, int>
     */
    public function countsFor(array $imageIds): array
    {
        $counts = array_fill_keys($imageIds, 0);

        foreach ($this->pageContents() as $content) {
            foreach ($imageIds as $imageId) {
                if ($this->references($content['html'], $imageId)) {
                    ++$counts[$imageId];
                }
            }
        }

        return $counts;
    }

    /**
     * Live pages using an image, with the locales they use it in.
     *
     * @return list<array{page: CmsPage, locales: list<string>}>
     */
    public function pagesUsing(int $imageId): array
    {
        $localesByPage = [];

        foreach ($this->pageContents() as $content) {
            if (!$this->references($content['html'], $imageId)) {
                continue;
            }

            $localesByPage[$content['pageId']][] = $content['locale'];
        }

        if ([] === $localesByPage) {
            return [];
        }

        $usages = [];

        foreach (CmsPageQuery::create()->filterById(array_keys($localesByPage), Criteria::IN)->find() as $page) {
            $usages[] = [
                'page' => $page,
                'locales' => $localesByPage[(int) $page->getId()],
            ];
        }

        return $usages;
    }

    /**
     * Draft and published HTML of every page not in the bin, one row per locale.
     *
     * The draft counts as a use: an image only referenced by an unpublished
     * draft is still expected to be there when that draft goes live.
     *
     * @return list<array{pageId: int, locale: string, html: string}>
     */
    private function pageContents(): array
    {
        $rows = CmsPageContentQuery::create()
            ->useCmsPageQuery()
                ->filterByDeletedAt(null, Criteria::ISNULL)
            ->endUse()
            ->select(['PageId', 'Locale', 'DraftHtml', 'PublishedHtml'])
            ->find()
            ->toArray();

        $contents = [];

        foreach ($rows as $row) {
            $contents[] = [
                'pageId' => (int) $row['PageId'],
                'locale' => (string) $row['Locale'],
                'html' => (string) $row['DraftHtml'].(string) $row['PublishedHtml'],
            ];
        }

        return $contents;
    }

    private function references(string $html, int $imageId): bool
    {
        return str_contains($html, '/image-library/'.$imageId.'/');
    }
}
