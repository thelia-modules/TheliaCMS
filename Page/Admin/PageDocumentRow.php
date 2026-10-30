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

/**
 * One document of a page on the edit screen. `title` is what a visitor reads,
 * `titleValue` what the editor wrote in the language being edited, empty when
 * the file is named after its original name. `moveUp` and `moveDown` are the
 * whole order the page would have after the move, null at the ends of the list.
 */
final readonly class PageDocumentRow
{
    /**
     * @param list<int>|null $moveUp
     * @param list<int>|null $moveDown
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $titleValue,
        public string $description,
        public string $originalName,
        public string $extension,
        public string $size,
        public string $url,
        public ?array $moveUp,
        public ?array $moveDown,
    ) {
    }
}
