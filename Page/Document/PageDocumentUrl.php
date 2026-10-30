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

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The address a document is downloaded from, relative to the site like the
 * other links of the module.
 *
 * The id and the stored name travel together and must name the same row: the
 * name alone would let anybody who guesses one read a file whatever its page,
 * the id alone would make every address of the site enumerable.
 */
final readonly class PageDocumentUrl
{
    public const string ROUTE = 'cms.page_document';

    public function __construct(
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function forDocument(int $documentId, string $file): string
    {
        return $this->urls->generate(self::ROUTE, ['id' => $documentId, 'file' => $file], UrlGeneratorInterface::ABSOLUTE_PATH);
    }
}
