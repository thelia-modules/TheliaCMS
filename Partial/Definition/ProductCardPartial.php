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

namespace TheliaCMS\Partial\Definition;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use TheliaCMS\Partial\Catalogue\CatalogueImage;
use TheliaCMS\Partial\Catalogue\ProductCardPrice;
use TheliaCMS\Partial\PartialDefinitionInterface;
use TheliaCMS\Partial\PartialProp;
use TheliaCMS\TheliaCMS;

/**
 * A product of the catalogue on a page, picked by search.
 *
 * The page stores the id of the product, never its price: the card is read
 * again on every visit, and the block leaves the page on its own once the
 * product is hidden or deleted. A theme that sells draws the card itself
 * (`cms/partials/product-card`), buy button and all; the module's own card
 * shows the picture, the name, a price and a link.
 */
final readonly class ProductCardPartial implements PartialDefinitionInterface
{
    public const string NAME = 'product-card';

    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urls,
        private ProductCardPrice $prices,
        private CatalogueImage $images,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return $this->trans('Product');
    }

    public function themeTemplate(): string
    {
        return 'cms/partials/product-card';
    }

    public function fallbackTemplate(): string
    {
        return '@TheliaCMSModule/front/partials/product-card.html.twig';
    }

    public function props(): array
    {
        return [
            PartialProp::reference(
                'product',
                $this->trans('Product'),
                source: $this->urls->generate('admin.cms.partials.sources.products'),
                help: $this->trans('Search by reference, name or barcode. Only products online are offered. The block leaves the page by itself if the product goes offline.'),
                searchable: true,
            ),
        ];
    }

    public function context(array $props, string $locale): array
    {
        $product = ProductQuery::create()
            ->filterById((int) $props['product'])
            ->filterByVisible(1)
            ->findOne();

        if (!$product instanceof Product) {
            return ['product' => null];
        }

        $product->setLocale($locale);

        return [
            'product' => [
                'id' => (int) $product->getId(),
                'ref' => (string) $product->getRef(),
                'title' => trim((string) $product->getTitle()),
                'url' => $product->getUrl($locale),
                'image' => $this->images->ofProduct((int) $product->getId(), $locale),
                'price' => $this->prices->of($product, $locale),
            ],
            // Labels travel with the data: a theme template may ignore them,
            // the fallback cannot reach the catalogue of the module otherwise.
            'labels' => [
                'see_product' => $this->trans('See the product', $locale),
            ],
        ];
    }

    /**
     * Never cached: prices and stock change without an event to drop a
     * fragment on, and a theme's card shows what the visitor has in their cart.
     */
    public function cacheTtl(): ?int
    {
        return null;
    }

    private function trans(string $message, ?string $locale = null): string
    {
        return $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME, $locale);
    }
}
