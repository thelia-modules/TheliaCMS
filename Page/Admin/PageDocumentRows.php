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

use TheliaCMS\Page\Document\PageDocument;
use TheliaCMS\Page\Document\PageDocumentRepository;
use TheliaCMS\Page\Document\PageDocumentUrl;

/**
 * The documents of a page shaped for the edit screen, in the order the editor
 * set.
 */
final readonly class PageDocumentRows
{
    public function __construct(
        private PageDocumentRepository $documents,
        private PageDocumentUrl $urls,
    ) {
    }

    /**
     * @return list<PageDocumentRow>
     */
    public function forPage(int $pageId, string $locale): array
    {
        return $this->present($this->documents->forPage($pageId, $locale), $locale);
    }

    /**
     * @param list<PageDocument> $documents
     *
     * @return list<PageDocumentRow>
     */
    public function present(array $documents, string $locale): array
    {
        $ids = array_map(static fn (PageDocument $document): int => $document->id, $documents);
        $rows = [];

        foreach ($documents as $index => $document) {
            // The repository names a file without a title after its original
            // name: that name is no text of the editor, the field stays empty.
            $bareName = pathinfo($document->originalName, \PATHINFO_FILENAME);
            $fallback = '' !== $bareName ? $bareName : $document->originalName;

            $rows[] = new PageDocumentRow(
                id: $document->id,
                title: $document->title,
                titleValue: $document->title === $fallback ? '' : $document->title,
                description: (string) $document->description,
                originalName: $document->originalName,
                extension: strtoupper($document->extension),
                size: PageDocumentSize::human($document->size, $locale),
                url: $this->urls->forDocument($document->id, $document->file),
                moveUp: $index > 0 ? self::swapped($ids, $index, $index - 1) : null,
                moveDown: $index < \count($ids) - 1 ? self::swapped($ids, $index, $index + 1) : null,
            );
        }

        return $rows;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private static function swapped(array $ids, int $from, int $to): array
    {
        [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];

        return $ids;
    }
}
