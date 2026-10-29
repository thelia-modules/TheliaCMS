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

namespace TheliaCMS\Content\Admin;

use Propel\Runtime\ActiveQuery\Criteria;
use TheliaCMS\Model\CmsItemContentQuery;
use TheliaCMS\Model\CmsPageContentQuery;

/**
 * How many pages use each component: what a person reads before putting one
 * away.
 *
 * The documents of every page out of the bin are read once, draft and
 * published, in every language: a component an unpublished draft uses is
 * one that page will go online with. A page using a component in two
 * languages, or in both its draft and what is online, counts once. The
 * document of a catalogue item counts as one page more: putting a component
 * away would break it the same way.
 */
final readonly class SectionUsageFinder
{
    /**
     * @return array<string, int> number of pages and catalogue items, keyed by component name; a component nothing uses is absent
     */
    public function pageCounts(): array
    {
        $rows = CmsPageContentQuery::create()
            ->useCmsPageQuery()
                ->filterByDeletedAt(null, Criteria::ISNULL)
            ->endUse()
            ->select(['PageId', 'DraftDocument', 'PublishedDocument'])
            ->find()
            ->toArray();

        $pagesByComponent = [];

        foreach ($rows as $row) {
            foreach ([$row['DraftDocument'], $row['PublishedDocument']] as $json) {
                foreach (self::components((string) $json) as $component) {
                    $pagesByComponent[$component]['page-'.$row['PageId']] = true;
                }
            }
        }

        $items = CmsItemContentQuery::create()
            ->select(['ItemType', 'ItemId', 'DraftDocument', 'PublishedDocument'])
            ->find()
            ->toArray();

        foreach ($items as $row) {
            foreach ([$row['DraftDocument'], $row['PublishedDocument']] as $json) {
                foreach (self::components((string) $json) as $component) {
                    $pagesByComponent[$component][$row['ItemType'].'-'.$row['ItemId']] = true;
                }
            }
        }

        return array_map(\count(...), $pagesByComponent);
    }

    /**
     * Read from the JSON rather than through Document: a document the
     * current catalogue would refuse still says which components it names.
     *
     * @return list<string>
     */
    private static function components(string $json): array
    {
        if ('' === $json) {
            return [];
        }

        $decoded = json_decode($json, true);
        $components = [];

        foreach (\is_array($decoded) && \is_array($decoded['sections'] ?? null) ? $decoded['sections'] : [] as $section) {
            if (\is_array($section) && \is_string($section['component'] ?? null)) {
                $components[] = $section['component'];
            }
        }

        return $components;
    }
}
