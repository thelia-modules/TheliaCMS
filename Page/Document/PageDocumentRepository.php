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

use Propel\Runtime\ActiveQuery\Criteria;
use TheliaCMS\Model\CmsPageDocumentQuery;
use TheliaCMS\Model\CmsPageQuery;

/**
 * The files of a page as a theme lists them: in the order the editor set,
 * with what was written of them in the language of the page.
 */
final readonly class PageDocumentRepository
{
    /**
     * @return list<PageDocument>
     */
    public function forPage(int $pageId, string $locale): array
    {
        $documents = CmsPageDocumentQuery::create()
            ->filterByPageId($pageId)
            ->joinWithI18n($locale, Criteria::LEFT_JOIN)
            ->orderByPosition()
            ->orderById()
            ->find();

        $list = [];

        foreach ($documents as $document) {
            $translation = $document->setLocale($locale)->getCurrentTranslation();
            $originalName = (string) $document->getOriginalName();
            $title = trim((string) $translation->getTitle());
            $description = trim((string) $translation->getDescription());

            $list[] = new PageDocument(
                id: (int) $document->getId(),
                title: '' !== $title ? $title : $this->nameOf($originalName),
                description: '' !== $description ? $description : null,
                file: (string) $document->getFile(),
                originalName: $originalName,
                extension: PageDocumentFormat::extensionOf((string) $document->getFile()),
                mimeType: (string) $document->getMimeType(),
                size: (int) $document->getSize(),
            );
        }

        return $list;
    }

    /**
     * The document a download address names, or null when the address does
     * not name one: no such row, a stored name that is not the row's, or a
     * page in the trash.
     *
     * The page being online is not asked: the back office links a file of a
     * draft, and the stored name carries eight random digits. The title is
     * the name of the file: no language is read here.
     */
    public function findForDownload(int $documentId, string $file): ?PageDocument
    {
        $document = CmsPageDocumentQuery::create()->findPk($documentId);

        if (null === $document || (string) $document->getFile() !== $file || !$this->pageIsLive((int) $document->getPageId())) {
            return null;
        }

        $originalName = (string) $document->getOriginalName();

        return new PageDocument(
            id: (int) $document->getId(),
            title: $this->nameOf($originalName),
            description: null,
            file: $file,
            originalName: $originalName,
            extension: PageDocumentFormat::extensionOf($file),
            mimeType: (string) $document->getMimeType(),
            size: (int) $document->getSize(),
        );
    }

    /**
     * Whether the page exists and is out of the trash.
     */
    public function pageIsLive(int $pageId): bool
    {
        return CmsPageQuery::create()
            ->filterById($pageId)
            ->filterByDeletedAt(null, Criteria::ISNULL)
            ->exists();
    }

    private function nameOf(string $originalName): string
    {
        $bareName = pathinfo($originalName, \PATHINFO_FILENAME);

        return '' !== $bareName ? $bareName : $originalName;
    }
}
