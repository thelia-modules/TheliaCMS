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
use TheliaCMS\Model\CmsBlockContentQuery;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageQuery;

/**
 * Which pages use which image.
 *
 * Editors delete images they believe unused, and a deleted image leaves broken
 * pictures behind. An image reaches a page two ways: as a URL inside its stored
 * HTML, which has no join to make, so the contents are read once and scanned in
 * memory for every image at a time — one query for a whole grid rather than one
 * per card — and as the image of the page, read from its column.
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
        // Page ids as keys: a page holding the image in two languages, or in its
        // content and as its image, is one page using it.
        $pagesByImage = array_fill_keys($imageIds, []);

        foreach ($this->pageContents() as $content) {
            foreach ($imageIds as $imageId) {
                if ($this->references($content['html'], $imageId)) {
                    $pagesByImage[$imageId][$content['pageId']] = true;
                }
            }
        }

        foreach ($this->pagesShowing($imageIds) as $pageId => $imageId) {
            $pagesByImage[$imageId][$pageId] = true;
        }

        return array_map(\count(...), $pagesByImage);
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

        // The image of a page is the same in every language, so it names none.
        foreach (array_keys($this->pagesShowing([$imageId])) as $pageId) {
            $localesByPage[$pageId] ??= [];
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
     * Number of live pages and live reusable blocks using an image.
     *
     * A block shows its images on every page it is placed on, so an image a
     * block holds is as much in use as one a page holds.
     */
    public function useCount(int $imageId): int
    {
        return \count($this->pagesUsing($imageId)) + $this->blockCount($imageId);
    }

    private function blockCount(int $imageId): int
    {
        $rows = CmsBlockContentQuery::create()
            ->useCmsBlockQuery()
                ->filterByDeletedAt(null, Criteria::ISNULL)
            ->endUse()
            ->select(['BlockId', 'DraftHtml', 'PublishedHtml', 'DraftProjectData'])
            ->find()
            ->toArray();

        $blockIds = [];

        foreach ($rows as $row) {
            if ($this->references((string) $row['DraftHtml'].(string) $row['PublishedHtml'].(string) $row['DraftProjectData'], $imageId)) {
                $blockIds[(int) $row['BlockId']] = true;
            }
        }

        return \count($blockIds);
    }

    /**
     * Stored content of every page not in the bin, one row per locale.
     *
     * Three columns, not one. The draft counts as a use — an image referenced
     * by an unpublished draft is still expected to be there when that draft
     * goes live — and the editor project is read too: it is what the canvas is
     * rebuilt from, so an image could live there before the exported HTML has
     * caught up. Missing a use would let an editor delete an image out from
     * under a page.
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

    /**
     * Live pages whose image is one of these, as page id => image id.
     *
     * @param list<int> $imageIds
     *
     * @return array<int, int>
     */
    private function pagesShowing(array $imageIds): array
    {
        if ([] === $imageIds) {
            return [];
        }

        $rows = CmsPageQuery::create()
            ->filterByDeletedAt(null, Criteria::ISNULL)
            ->filterByImageId($imageIds, Criteria::IN)
            ->select(['Id', 'ImageId'])
            ->find()
            ->toArray();

        $pages = [];

        foreach ($rows as $row) {
            $pages[(int) $row['Id']] = (int) $row['ImageId'];
        }

        return $pages;
    }

    private function references(string $html, int $imageId): bool
    {
        // The editor project is JSON, and a JSON encoder is free to write the
        // slashes of a URL escaped.
        return str_contains(str_replace('\\/', '/', $html), '/image-library/'.$imageId.'/');
    }
}
