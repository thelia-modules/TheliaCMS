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

use Thelia\Model\Product;

/**
 * What a card of the module shows of a product: its name, its picture, a
 * price and a link. The same for one product on its own and for a strip of
 * them, so a theme that draws its own cards reads the same keys everywhere.
 */
final readonly class ProductSummary
{
    public function __construct(
        private ProductCardPrice $prices,
        private CatalogueImage $images,
    ) {
    }

    /**
     * @return array{id: int, ref: string, title: string, url: string, image: string|null, price: array<string, mixed>|null}
     */
    public function of(Product $product, string $locale): array
    {
        $product->setLocale($locale);

        return [
            'id' => (int) $product->getId(),
            'ref' => (string) $product->getRef(),
            'title' => trim((string) $product->getTitle()),
            'url' => $product->getUrl($locale),
            'image' => $this->images->ofProduct((int) $product->getId(), $locale),
            'price' => $this->prices->of($product, $locale),
        ];
    }
}
