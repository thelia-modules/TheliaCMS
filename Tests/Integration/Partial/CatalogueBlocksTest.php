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

use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Model\Category;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Content\Partial\PartialSection;
use TheliaCMS\Generation\CatalogSheet;
use TheliaCMS\Partial\Definition\CategoryCardPartial;
use TheliaCMS\Partial\Definition\CategoryStripPartial;
use TheliaCMS\Partial\Definition\ProductCardPartial;
use TheliaCMS\Partial\Definition\ProductStripPartial;
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
        $online->getDefaultSaleElements()->setEanCode('3999999999990')->save();
        $offline = $this->product('CMSBLK-OFFLINE', 'Zucchini catalogue block hidden', 0);

        self::assertSame([(int) $online->getId()], $this->ids('products', ['q' => 'CMSBLK-']), 'By the start of the reference, online only.');
        self::assertSame([(int) $online->getId()], $this->ids('products', ['q' => 'catalogue block']), 'By a part of the name, online only.');
        self::assertSame([(int) $online->getId()], $this->ids('products', ['q' => '3999999999990']), 'By the barcode of a sale element.');
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

    public function testAStripShowsThePickedProductsInTheirOrderWithoutTheOnesOffline(): void
    {
        $first = $this->product('CMSBLK-STRIP-1', 'Strip one', 1);
        $second = $this->product('CMSBLK-STRIP-2', 'Strip two', 1);
        $third = $this->product('CMSBLK-STRIP-3', 'Strip three', 1);
        $partial = $this->getService(ProductStripPartial::class);
        \assert($partial instanceof ProductStripPartial);
        $ids = [(int) $third->getId(), (int) $first->getId(), (int) $second->getId()];

        $context = $partial->context(['heading' => 'Our picks', 'products' => $ids], 'en_US');

        self::assertSame('Our picks', $context['heading']);
        self::assertSame($ids, $context['product_ids'], 'The order is the one they were picked in, not the one of the database.');
        self::assertSame(['Strip three', 'Strip one', 'Strip two'], array_column($context['products'], 'title'));
        self::assertNotNull($context['products'][0]['price']);

        $first->setVisible(0)->save();

        self::assertSame([(int) $third->getId(), (int) $second->getId()], $partial->context(['products' => $ids, 'heading' => null], 'en_US')['product_ids'], 'An offline product leaves the row without a hole.');

        // Read as the model would, whether or not the theme puts the strip away for a component of its own.
        $sheet = $this->getService(CatalogSheet::class)->section(new PartialSection($partial, $this->getService(TranslatorInterface::class)));
        self::assertStringContainsString('## `Partial:product-strip`', $sheet, 'The strip has its sheet in the catalogue.');
        self::assertStringContainsString('`products`* (list of 2 to 12 items)', $sheet);
        self::assertStringContainsString('`product`* (id from the search tools)', $sheet);
        self::assertStringContainsString('"products":[{"product":1},{"product":2}]', $sheet, 'The example shows the shape of a row.');
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

    public function testAStripOfCategoriesKeepsTheOrderAndDropsTheOnesOffline(): void
    {
        $first = $this->category('Strip category one', 0, 1);
        $second = $this->category('Strip category two', 0, 1);
        $partial = $this->getService(CategoryStripPartial::class);
        \assert($partial instanceof CategoryStripPartial);
        $ids = [(int) $second->getId(), (int) $first->getId()];

        $context = $partial->context(['heading' => 'Our worlds', 'categories' => $ids], 'en_US');

        self::assertSame(['Strip category two', 'Strip category one'], array_column($context['categories'], 'title'));
        self::assertSame('Our worlds', $context['heading']);

        $second->setVisible(0)->save();

        self::assertSame([(int) $first->getId()], array_column($partial->context(['categories' => $ids, 'heading' => null], 'en_US')['categories'], 'id'));
        self::assertStringContainsString('## `Partial:category-strip`', $this->getService(CatalogSheet::class)->section(new PartialSection($partial, $this->getService(TranslatorInterface::class))));
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
