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

use Thelia\Model\Lang;
use TheliaCMS\Media\CmsMediaLibrary;
use TheliaLibrary\Model\LibraryImage;

/**
 * Builds what the media screens display, so the controller only has to render.
 */
final readonly class MediaCatalog
{
    /** Width of the grid thumbnails, in pixels. */
    private const int THUMBNAIL_WIDTH = 320;

    public function __construct(
        private CmsMediaLibrary $library,
        private MediaUsageFinder $usages,
    ) {
    }

    /**
     * @return list<MediaItem>
     */
    public function grid(string $locale, ?string $search = null): array
    {
        $images = $this->library->images($search);

        if ([] === $images) {
            return [];
        }

        $counts = $this->usages->countsFor(array_map(static fn (LibraryImage $image): int => (int) $image->getId(), $images));

        $items = [];

        foreach ($images as $image) {
            $item = $this->item($image, $locale, $counts[(int) $image->getId()] ?? 0);

            if (null !== $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function item(LibraryImage $image, string $locale, ?int $usageCount = null): ?MediaItem
    {
        $this->library->measure($image);

        $url = $this->library->publicUrl($image);

        if (null === $url) {
            return null;
        }

        $fileName = (string) $image->getFileName();

        return new MediaItem(
            id: (int) $image->getId(),
            title: $this->titleOf($image, $locale, $fileName),
            url: $url,
            thumbnailUrl: $this->thumbnailUrl($image, $fileName),
            alt: $image->getAlt(),
            decorative: 1 === $image->getDecorative(),
            caption: $image->getCaption(),
            width: $image->getWidth(),
            height: $image->getHeight(),
            fileSize: $image->getFileSize(),
            format: '' !== pathinfo($fileName, \PATHINFO_EXTENSION) ? pathinfo($fileName, \PATHINFO_EXTENSION) : null,
            tags: $this->library->tagsOf($image),
            usageCount: $usageCount ?? $this->usages->countsFor([(int) $image->getId()])[(int) $image->getId()],
        );
    }

    /**
     * Name to show for an image, in the language being edited.
     *
     * An image named in one language only would otherwise show as its stored
     * file name — a random prefix — in the others, so the shop default answers
     * first. Leaves the model on the requested locale: the caller reads the
     * alternative text and caption from it straight after.
     */
    private function titleOf(LibraryImage $image, string $locale, string $fileName): string
    {
        foreach ([$locale, Lang::getDefaultLanguage()->getLocale()] as $candidate) {
            $title = $image->setLocale($candidate)->getTitle();

            if (null !== $title && '' !== $title) {
                $image->setLocale($locale);

                return $title;
            }
        }

        $image->setLocale($locale);

        return $fileName;
    }

    /**
     * A thumbnail is only asked for when it would actually be smaller: the
     * image route refuses to upscale and answers 400.
     */
    private function thumbnailUrl(LibraryImage $image, string $fileName): ?string
    {
        $width = $image->getWidth();

        if (null === $width || $width <= self::THUMBNAIL_WIDTH) {
            return $this->library->publicUrl($image);
        }

        $format = pathinfo($fileName, \PATHINFO_EXTENSION);

        return '/image-library/'.$image->getId().'/full/'.self::THUMBNAIL_WIDTH.',!/0/default.'.($format ?: 'jpg');
    }
}
