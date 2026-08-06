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

/**
 * A library image as the publish pipeline needs it: its identifier, its stored
 * format and the size it really has on disk.
 *
 * The dimensions are read from the file because TheliaLibrary does not store
 * them — a gap the upstream schema change is meant to close.
 */
final readonly class MediaFile
{
    public function __construct(
        public int $id,
        public string $format,
        public ?int $width = null,
        public ?int $height = null,
    ) {
    }
}
