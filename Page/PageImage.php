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

namespace TheliaCMS\Page;

/**
 * The image of a page, as a theme reads it: the address of the stored file and
 * what to write in its `alt` attribute.
 *
 * `alt` is an empty string for an image marked decorative, and null for one
 * nobody has described yet: a theme can tell the two apart, and an empty
 * attribute is only right for the first.
 */
final readonly class PageImage
{
    public function __construct(
        public int $id,
        public string $url,
        public ?string $alt,
        public ?int $width,
        public ?int $height,
    ) {
    }
}
