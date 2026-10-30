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

/**
 * A file of a page, as a theme reads it in one language.
 *
 * `title` is never empty: the title written in that language, or the name the
 * file was sent under, less its extension. There is no fallback to another
 * language: a title in French under a page read in English is worse than the
 * name of the file.
 */
final readonly class PageDocument
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public string $file,
        public string $originalName,
        public string $extension,
        public string $mimeType,
        public int $size,
    ) {
    }
}
