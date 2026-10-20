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

namespace TheliaCMS\Content\Section;

use TheliaCMS\Content\PropSchema;
use TheliaCMS\Media\CmsMediaLibrary;
use TheliaLibrary\Model\LibraryImageQuery;

/**
 * An image prop, resolved for a template: the address the image is served
 * at and its dimensions. A document names an image by its library id, never
 * by a path, so a renamed or replaced file is still the right image.
 *
 * The address is the full-size one: the image rewriter composes the srcset
 * and the dimensions when the page is published.
 */
final readonly class SectionImages
{
    public function __construct(
        private CmsMediaLibrary $library,
    ) {
    }

    /**
     * @return array{id: int|null, url: string, width: int|null, height: int|null}|null null when the id names no image
     */
    public function resolve(mixed $imageId): ?array
    {
        // A picture kept elsewhere: served as it is, its size unknown here.
        if (\is_string($imageId) && PropSchema::isExternalImage($imageId)) {
            return ['id' => null, 'url' => trim($imageId), 'width' => null, 'height' => null];
        }

        if (!\is_int($imageId) || $imageId <= 0) {
            return null;
        }

        $image = LibraryImageQuery::create()->findPk($imageId);

        if (null === $image) {
            return null;
        }

        $url = $this->library->publicUrl($image);

        if (null === $url) {
            return null;
        }

        return [
            'id' => $imageId,
            'url' => $url,
            'width' => $image->getWidth(),
            'height' => $image->getHeight(),
        ];
    }
}
