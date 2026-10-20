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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use TheliaCMS\Partial\Catalogue\ProductSummary;
use TheliaCMS\Partial\PartialDefinitionInterface;
use TheliaCMS\Partial\PartialProp;
use TheliaCMS\TheliaCMS;

/**
 * Several products of the catalogue on a page, side by side in one row, in
 * the order they were picked: a selection, the accessories of something,
 * what goes with a story.
 *
 * The page stores the ids, never a price: the strip is read again on every
 * visit, and a product hidden or deleted since leaves it without a hole. A
 * theme that sells draws the row itself (`cms/partials/product-strip`), with
 * the cards of its catalogue; the module's own row shows its cards.
 */
final readonly class ProductStripPartial implements PartialDefinitionInterface
{
    public const string NAME = 'product-strip';

    public const int MIN_PRODUCTS = 2;
    public const int MAX_PRODUCTS = 12;

    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urls,
        private ProductSummary $summaries,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return $this->trans('Products in a row');
    }

    public function themeTemplate(): string
    {
        return 'cms/partials/product-strip';
    }

    public function fallbackTemplate(): string
    {
        return '@TheliaCMSModule/front/partials/product-strip.html.twig';
    }

    public function props(): array
    {
        return [
            PartialProp::text('heading', $this->trans('Heading'), required: false, help: $this->trans('Shown above the row. Leave empty for no heading.')),
            PartialProp::references(
                'products',
                $this->trans('Products'),
                source: $this->urls->generate('admin.cms.partials.sources.products'),
                itemName: 'product',
                min: self::MIN_PRODUCTS,
                max: self::MAX_PRODUCTS,
                help: $this->trans('Two to twelve products, shown in this order. Search by reference, name or barcode; only products online are offered, and one that goes offline leaves the row by itself.'),
                searchable: true,
                itemLabel: $this->trans('Product'),
            ),
        ];
    }

    public function context(array $props, string $locale): array
    {
        /** @var list<int> $ids */
        $ids = \is_array($props['products'] ?? null) ? $props['products'] : [];

        $byId = [];

        if ([] !== $ids) {
            foreach (ProductQuery::create()->filterById($ids, Criteria::IN)->filterByVisible(1)->find() as $product) {
                $byId[(int) $product->getId()] = $product;
            }
        }

        $products = [];

        // In the order they were picked: the API of the database answers in
        // its own.
        foreach ($ids as $id) {
            if (($byId[$id] ?? null) instanceof Product) {
                $products[] = $this->summaries->of($byId[$id], $locale);
            }
        }

        return [
            'heading' => $props['heading'],
            'products' => $products,
            'product_ids' => array_column($products, 'id'),
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
