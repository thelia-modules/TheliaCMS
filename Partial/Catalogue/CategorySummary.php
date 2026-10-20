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

namespace TheliaCMS\Partial\Catalogue;

use Thelia\Model\Category;

/**
 * What a card of the module shows of a category: its name, its picture and
 * a link. The same for one category on its own and for a row of them.
 */
final readonly class CategorySummary
{
    public function __construct(
        private CatalogueImage $images,
    ) {
    }

    /**
     * @return array{id: int, title: string, url: string, image: string|null}
     */
    public function of(Category $category, string $locale): array
    {
        $category->setLocale($locale);

        return [
            'id' => (int) $category->getId(),
            'title' => trim((string) $category->getTitle()),
            'url' => $category->getUrl($locale),
            'image' => $this->images->ofCategory((int) $category->getId(), $locale),
        ];
    }
}
