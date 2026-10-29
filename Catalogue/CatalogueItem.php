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

namespace TheliaCMS\Catalogue;

/**
 * A product, a category, a content or a folder, read in one language: what
 * the screens and the generator need to know of the item a document belongs
 * to.
 */
final readonly class CatalogueItem
{
    public function __construct(
        public CatalogueItemType $type,
        public int $id,
        public string $locale,
        public string $title,
        public ?string $summary,
        public ?string $description,
        public ?string $reference,
        public ?string $url,
        public bool $online = true,
    ) {
    }
}
