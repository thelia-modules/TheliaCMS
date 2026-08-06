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

use Symfony\Component\HttpFoundation\File\UploadedFile;
use TheliaCMS\Media\CmsMediaLibrary;
use TheliaLibrary\Model\LibraryImage;
use TheliaLibrary\Service\LibraryImageService;

/**
 * Every write the media screens perform. Nothing is persisted in a controller.
 */
final readonly class CmsMediaWriter
{
    public function __construct(
        private LibraryImageService $images,
        private CmsMediaLibrary $library,
    ) {
    }

    /**
     * Stores uploaded files and marks them as CMS content.
     *
     * @param list<UploadedFile> $files
     *
     * @return list<LibraryImage>
     */
    public function add(array $files, string $locale): array
    {
        $stored = [];

        foreach ($files as $file) {
            $image = $this->images->createImage($file, $file->getClientOriginalName(), $locale);
            $this->library->tag($image);

            $stored[] = $image;
        }

        return $stored;
    }

    /**
     * @param array{title: ?string, alt: ?string, caption: ?string, decorative: ?bool, tags: ?string, file: ?UploadedFile} $data
     */
    public function save(LibraryImage $image, string $locale, array $data): void
    {
        if ($data['file'] instanceof UploadedFile) {
            // Replacing goes through the library so the old file is removed and
            // the new one is measured; the row, and therefore the URL every
            // published page points at, is kept.
            $this->images->updateImage($image->getId(), $data['file'], null, $locale);
            $image->reload();
        }

        $image->setDecorative(true === $data['decorative'] ? 1 : 0);

        $image->setLocale($locale)
            ->setAlt($data['alt'])
            ->setCaption($data['caption']);

        $title = null === $data['title'] ? '' : trim($data['title']);

        if ('' !== $title) {
            $image->setTitle($title);
        }

        $image->save();

        $this->library->retag($image, $this->splitTags($data['tags']));
    }

    public function delete(LibraryImage $image): void
    {
        $this->images->deleteImage($image->getId());
    }

    /**
     * @return list<string>
     */
    private function splitTags(?string $tags): array
    {
        if (null === $tags || '' === trim($tags)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $tags)), static fn (string $tag): bool => '' !== $tag));
    }
}
