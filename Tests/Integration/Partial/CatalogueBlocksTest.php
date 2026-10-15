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

namespace TheliaCMS\Tests\Integration\Partial;

use Thelia\Model\Category;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Partial\Definition\CategoryCardPartial;
use TheliaCMS\Partial\Definition\ProductCardPartial;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * The "Product" and "Category" blocks: what their searched sources offer an
 * editor, and what reaches the template once one is picked.
 */
final class CatalogueBlocksTest extends AdminScreenTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new FixtureFactory($this->getPropelConnection());
    }

    public function testTheProductSourceFindsByReferenceNameAndBarcodeAndOffersOnlyProductsOnline(): void
    {
        $online = $this->product('CMSBLK-ONLINE', 'Zucchini catalogue block', 1);
        $online->getDefaultSaleElements()->setEanCode('3999999999991')->save();
        $offline = $this->product('CMSBLK-OFFLINE', 'Zucchini catalogue block hidden', 0);

        self::assertSame([(int) $online->getId()], $this->ids('products', ['q' => 'CMSBLK-']), 'By the start of the reference, online only.');
        self::assertSame([(int) $online->getId()], $this->ids('products', ['q' => 'catalogue block']), 'By a part of the name, online only.');
        self::assertSame([(int) $online->getId()], $this->ids('products', ['q' => '3999999999991']), 'By the barcode of a sale element.');
        self::assertSame([], $this->ids('products', ['q' => 'C']), 'One character is not a search.');
        self::assertSame([], $this->ids('products', ['q' => '%%']), 'A wildcard is searched for, not obeyed.');

        $chosen = $this->answer('products', ['id' => (int) $offline->getId()]);
        self::assertCount(1, $chosen, 'The product already chosen is read back, offline or not.');
        self::assertStringContainsString('Zucchini catalogue block hidden (CMSBLK-OFFLINE)', $chosen[0]['name']);
        self::assertNotSame('Zucchini catalogue block hidden (CMSBLK-OFFLINE)', $chosen[0]['name'], 'An offline product says so.');
        self::assertSame('Zucchini catalogue block (CMSBLK-ONLINE)', $this->answer('products', ['id' => (int) $online->getId()])[0]['name'], 'An online one is named alone.');
        self::assertSame([], $this->answer('products', ['id' => 999999999]));
    }

    public function testTheCategorySourceNamesEachCategoryWithItsParentAndOffersOnlyCategoriesOnline(): void
    {
        $parent = $this->category('Catalogue block aisle', 0, 1);
        $child = $this->category('Catalogue block shelf', (int) $parent->getId(), 1);
        $this->category('Catalogue block shelf hidden', (int) $parent->getId(), 0);

        $found = $this->answer('categories', ['q' => 'Catalogue block shelf']);

        self::assertSame([(int) $child->getId()], array_column($found, 'id'), 'Online only.');
        self::assertSame('Catalogue block shelf (Catalogue block aisle)', $found[0]['name'], 'Named with its parent.');
    }

    public function testTheSourcesNeedTheRightToSeeThePages(): void
    {
        $this->logInAs($this->factory->restrictedAdmin([]));

        self::assertSame(403, $this->send('GET', '/admin/cms/partials/sources/products?q=ab', [], self::XHR));
        self::assertSame(403, $this->send('GET', '/admin/cms/partials/sources/categories?q=ab', [], self::XHR));
    }

    public function testAPickedProductReachesTheTemplateUntilItGoesOffline(): void
    {
        $product = $this->product('CMSBLK-CARD', 'Catalogue block card', 1);
        $partial = $this->getService(ProductCardPartial::class);
        \assert($partial instanceof ProductCardPartial);

        $context = $partial->context(['product' => (int) $product->getId()], 'en_US');

        self::assertSame((int) $product->getId(), $context['product']['id']);
        self::assertSame('Catalogue block card', $context['product']['title']);
        self::assertNotSame('', $context['product']['url']);
        self::assertNotNull($context['product']['price'], 'The default sale element has a price in the default currency.');

        $product->setVisible(0)->save();

        self::assertNull($partial->context(['product' => (int) $product->getId()], 'en_US')['product'], 'An offline product leaves the page.');
    }

    public function testAPickedCategoryReachesTheTemplateUntilItGoesOffline(): void
    {
        $category = $this->category('Catalogue block picked', 0, 1);
        $partial = $this->getService(CategoryCardPartial::class);
        \assert($partial instanceof CategoryCardPartial);

        $context = $partial->context(['category' => (int) $category->getId()], 'en_US');

        self::assertSame('Catalogue block picked', $context['category']['title']);

        $category->setVisible(0)->save();

        self::assertNull($partial->context(['category' => (int) $category->getId()], 'en_US')['category']);
    }

    /**
     * @param array<string, string|int> $query
     *
     * @return list<array{id: int, name: string}>
     */
    private function answer(string $source, array $query): array
    {
        $this->client->request('GET', '/admin/cms/partials/sources/'.$source, $query, [], self::XHR);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, string|int> $query
     *
     * @return list<int>
     */
    private function ids(string $source, array $query): array
    {
        return array_column($this->answer($source, $query), 'id');
    }

    private function product(string $ref, string $title, int $visible): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['ref' => $ref, 'title' => $title, 'locale' => $this->locale(), 'visible' => $visible],
        );
    }

    private function category(string $title, int $parent, int $visible): Category
    {
        $category = $this->factory->category(['parent' => $parent, 'visible' => $visible]);
        $category->setLocale($this->locale())->setTitle($title)->save();

        return $category;
    }
}
