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
 * A file of a page as a template draws it: what to print and where to link.
 *
 * `title` is never empty (see PageDocument). `size` is in bytes: formatting it
 * is the theme's business, in the language it serves.
 */
final readonly class PageDocumentLink
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public string $url,
        public string $extension,
        public int $size,
        public string $originalName,
    ) {
    }
}
