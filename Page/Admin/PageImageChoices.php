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

use TheliaCMS\Media\Admin\MediaCatalog;
use TheliaCMS\Media\Admin\MediaItem;
use TheliaCMS\Media\CmsMediaLibrary;
use TheliaCMS\Model\CmsPage;
use TheliaLibrary\Model\LibraryImage;

/**
 * The images the "Image" tab of a page offers: the CMS library, most recent
 * first, as the media screen lists it.
 *
 * The library listing is capped, so the image a page already has is added when
 * it falls past the cap: otherwise saving the page would silently drop it.
 */
final readonly class PageImageChoices
{
    public function __construct(
        private CmsMediaLibrary $library,
        private MediaCatalog $catalog,
    ) {
    }

    /**
     * @return array<int, MediaItem> keyed by image id
     */
    public function for(CmsPage $page, string $locale): array
    {
        $images = $this->library->images();
        $currentId = $page->getImageId();

        if (null !== $currentId && [] === array_filter($images, static fn (LibraryImage $image): bool => (int) $image->getId() === $currentId)) {
            $current = $this->library->ownedImage($currentId);

            if (null !== $current) {
                $images[] = $current;
            }
        }

        $items = [];

        foreach ($images as $image) {
            // The usage count is not shown here, and computing it reads the
            // content of every page.
            $item = $this->catalog->item($image, $locale, 0);

            if (null !== $item) {
                $items[$item->id] = $item;
            }
        }

        return $items;
    }
}
