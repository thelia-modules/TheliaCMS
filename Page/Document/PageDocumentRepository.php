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
            $bareName = pathinfo($originalName, \PATHINFO_FILENAME);

            $list[] = new PageDocument(
                id: (int) $document->getId(),
                title: '' !== $title ? $title : ('' !== $bareName ? $bareName : $originalName),
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
}
