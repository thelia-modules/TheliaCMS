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

namespace TheliaCMS\Page\Document;

/**
 * The files of a page ready to be linked: the list of the repository, each
 * with its address.
 *
 * Routing stays out of the repository, which reads rows and nothing else.
 */
final readonly class PageDocumentLinks
{
    public function __construct(
        private PageDocumentRepository $documents,
        private PageDocumentUrl $urls,
    ) {
    }

    /**
     * A page unknown or in the trash has no document to offer.
     *
     * @return list<PageDocumentLink>
     */
    public function forPage(int $pageId, string $locale): array
    {
        if ($pageId <= 0 || !$this->documents->pageIsLive($pageId)) {
            return [];
        }

        return array_map(
            fn (PageDocument $document): PageDocumentLink => new PageDocumentLink(
                id: $document->id,
                title: $document->title,
                description: $document->description,
                url: $this->urls->forDocument($document->id, $document->file),
                extension: $document->extension,
                size: $document->size,
                originalName: $document->originalName,
            ),
            $this->documents->forPage($pageId, $locale),
        );
    }
}
