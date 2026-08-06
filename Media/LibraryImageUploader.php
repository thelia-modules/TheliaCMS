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

use OpenStudio\PageBuilderBundle\Contract\Exception\ImageUploadException;
use OpenStudio\PageBuilderBundle\Contract\ImageUploadPortInterface;
use OpenStudio\PageBuilderBundle\Dto\ImageUploadResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use TheliaLibrary\Service\LibraryImageService;

/**
 * Stores what the editor uploads in TheliaLibrary.
 */
final readonly class LibraryImageUploader implements ImageUploadPortInterface
{
    public function __construct(
        private LibraryImageService $images,
        private CmsMediaLibrary $library,
    ) {
    }

    public function upload(UploadedFile $file, ?string $context = null, ?string $uploadedBy = null): ImageUploadResponse
    {
        $originalName = $file->getClientOriginalName();

        // Read before storing: createImage() moves the file away, and the
        // dimensions are what keeps the published page from shifting as images
        // load.
        $dimensions = @getimagesize($file->getPathname());

        try {
            $image = $this->images->createImage($file, $originalName, $this->library->locale());
        } catch (\Throwable $throwable) {
            throw new ImageUploadException($throwable->getMessage(), 0, $throwable);
        }

        $this->library->tag($image);
        $this->library->shareFileNameAcrossLocales($image);

        $url = $this->library->publicUrl($image);

        if (null === $url) {
            throw new ImageUploadException(\sprintf('The image "%s" was stored but has no public URL.', $originalName));
        }

        return new ImageUploadResponse(
            id: (string) $image->getId(),
            url: $url,
            originalFileName: $originalName,
            width: false !== $dimensions ? $dimensions[0] : null,
            height: false !== $dimensions ? $dimensions[1] : null,
        );
    }

    public function delete(string $imageId): void
    {
        $this->images->deleteImage($imageId);
    }
}
