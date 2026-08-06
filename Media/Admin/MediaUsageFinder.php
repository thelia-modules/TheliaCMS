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

    private function references(string $html, int $imageId): bool
    {
        // The editor project is JSON, and a JSON encoder is free to write the
        // slashes of a URL escaped.
        return str_contains(str_replace('\\/', '/', $html), '/image-library/'.$imageId.'/');
    }
}
