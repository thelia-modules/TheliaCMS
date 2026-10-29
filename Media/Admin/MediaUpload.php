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

use TheliaLibrary\Model\LibraryImage;

/**
 * What a sending of files gave: the images stored, and each file refused with
 * why. One refused file never costs the others.
 */
final readonly class MediaUpload
{
    /**
     * @param list<LibraryImage>                                     $stored
     * @param list<array{name: string, refusal: MediaUploadRefusal}> $refused
     */
    public function __construct(
        public array $stored,
        public array $refused,
    ) {
    }

    /**
     * @return list<int>
     */
    public function storedIds(): array
    {
        return array_map(static fn (LibraryImage $image): int => (int) $image->getId(), $this->stored);
    }
}
