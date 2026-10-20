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

namespace TheliaCMS\Tests\Unit\Partial;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\Partial\Catalogue\CatalogueImage;
use TheliaCMS\Partial\Catalogue\CategorySummary;
use TheliaCMS\Partial\Catalogue\ProductCardPrice;
use TheliaCMS\Partial\Catalogue\ProductSummary;
use TheliaCMS\Partial\Definition\CategoryCardPartial;
use TheliaCMS\Partial\Definition\CategoryStripPartial;
use TheliaCMS\Partial\Definition\ProductCardPartial;
use TheliaCMS\Partial\Definition\ProductStripPartial;
use TheliaCMS\Partial\PartialDefinitionInterface;
use TheliaCMS\Partial\PartialPropType;

/**
 * What a page stores for the "Product" and "Category" blocks, and why they are
 * never cached.
 */
final class CatalogueBlocksTest extends TestCase
{
    public function testTheProductIsPickedBySearchAndNeverCached(): void
    {
        $this->assertPickedBySearch($this->product(), 'product', 'admin.cms.partials.sources.products');
    }

    public function testTheStripTakesTwoToTwelveProductsPickedBySearchAndIsNeverCached(): void
    {
        $strip = $this->strip();
        $products = $strip->props()[1];

        self::assertSame('product-strip', $strip->name());
        self::assertSame(PartialPropType::References, $products->type);
        self::assertSame('product', $products->itemName);
        self::assertSame([2, 12], [$products->min, $products->max]);
        self::assertTrue($products->required, 'A row of nothing is no row.');
        self::assertTrue($products->searchable);
        self::assertStringContainsString('sources.products', (string) $products->source);
        self::assertNull($strip->cacheTtl(), 'Prices change without an event to drop a fragment on.');
    }

    public function testTheCategoryStripTakesTwoToTwelveCategoriesPickedBySearchAndIsNeverCached(): void
    {
        $strip = $this->categoryStrip();
        $categories = $strip->props()[1];

        self::assertSame('category-strip', $strip->name());
        self::assertSame(PartialPropType::References, $categories->type);
        self::assertSame('category', $categories->itemName);
        self::assertSame([2, 12], [$categories->min, $categories->max]);
        self::assertTrue($categories->searchable);
        self::assertStringContainsString('sources.categories', (string) $categories->source);
        self::assertNull($strip->cacheTtl());
    }

    public function testTheCategoryIsPickedBySearchAndNeverCached(): void
    {
        $this->assertPickedBySearch($this->category(), 'category', 'admin.cms.partials.sources.categories');
    }

    private function assertPickedBySearch(PartialDefinitionInterface $partial, string $setting, string $route): void
    {
        $props = $partial->props();

        self::assertCount(1, $props);
        self::assertSame($setting, $props[0]->name);
        self::assertSame(PartialPropType::Reference, $props[0]->type, 'The page stores an id.');
        self::assertTrue($props[0]->searchable, 'A catalogue is too long for a select.');
        self::assertTrue($props[0]->required);
        self::assertSame('/'.$route, $props[0]->source);
        self::assertNull($partial->cacheTtl(), 'Prices, stock and imports change without an event to drop a fragment on.');
        self::assertStringStartsWith('@TheliaCMSModule/front/partials/', $partial->fallbackTemplate());
    }

    private function product(): ProductCardPartial
    {
        return new ProductCardPartial($this->translator(), $this->urls(), $this->summaries());
    }

    private function strip(): ProductStripPartial
    {
        return new ProductStripPartial($this->translator(), $this->urls(), $this->summaries());
    }

    private function summaries(): ProductSummary
    {
        return new ProductSummary(
            (new \ReflectionClass(ProductCardPrice::class))->newInstanceWithoutConstructor(),
            (new \ReflectionClass(CatalogueImage::class))->newInstanceWithoutConstructor(),
        );
    }

    private function category(): CategoryCardPartial
    {
        return new CategoryCardPartial($this->translator(), $this->urls(), $this->categorySummaries());
    }

    private function categoryStrip(): CategoryStripPartial
    {
        return new CategoryStripPartial($this->translator(), $this->urls(), $this->categorySummaries());
    }

    private function categorySummaries(): CategorySummary
    {
        return new CategorySummary((new \ReflectionClass(CatalogueImage::class))->newInstanceWithoutConstructor());
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }

    private function urls(): UrlGeneratorInterface
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route): string => '/'.$route);

        return $urls;
    }
}
