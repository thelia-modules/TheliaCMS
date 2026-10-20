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
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * The searched sources of products and categories: what they offer an editor
 * picking one for a component of the theme.
 */
final class CatalogueSourcesTest extends AdminScreenTestCase
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
