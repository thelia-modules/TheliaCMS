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

namespace TheliaCMS\Media;

use OpenStudio\PageBuilderBundle\Contract\ImageLibraryPortInterface;
use OpenStudio\PageBuilderBundle\Dto\ImageRecord;
use TheliaLibrary\Service\LibraryImageService;

/**
 * Feeds the editor asset manager with the CMS images already uploaded.
 */
final readonly class LibraryImageCatalog implements ImageLibraryPortInterface
{
    public function __construct(
        private LibraryImageService $images,
        private CmsMediaLibrary $library,
    ) {
    }

    /**
     * The context identifies the page being edited, but the library is
     * deliberately shared: an image uploaded for one page is offered on all of
     * them, which is what an editor expects of a media library.
     *
     * @return list<ImageRecord>
     */
    public function findByContext(string $context): array
    {
        $records = [];
        $locale = $this->library->locale();

        foreach ($this->library->images() as $image) {
            $url = $this->library->publicUrl($image);

            if (null === $url) {
                continue;
            }

            $records[] = new ImageRecord(
                id: (string) $image->getId(),
                url: $url,
                name: $image->setLocale($locale)->getTitle(),
            );
        }

        return $records;
    }

    public function delete(string $imageId): void
    {
        $this->images->deleteImage($imageId);
    }
}
