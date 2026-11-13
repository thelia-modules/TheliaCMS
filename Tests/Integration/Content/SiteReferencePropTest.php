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

namespace TheliaCMS\Tests\Integration\Content;

use Thelia\Model\Lang;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Content\Document;
use TheliaCMS\Content\Section;
use TheliaCMS\Content\Site\SiteComponent;
use TheliaCMS\Content\Site\SiteComponentRepository;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Page\Admin\CmsPageWriter;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * A `reference` prop of a component of the site in the back office: the
 * editor picks the record from the list of its kind, at the top of the
 * component and in the rows of a list, and the page stores its id.
 */
final class SiteReferencePropTest extends AdminScreenTestCase
{
    public function testTheEditorPicksTheRecordsFromTheListOfTheirKindAndThePageStoresTheirIds(): void
    {
        $fixtures = new FixtureFactory($this->getPropelConnection());
        $category = $fixtures->category();
        $product = $fixtures->product($category, $fixtures->taxRule(), $fixtures->currency());
        $other = $fixtures->product($category, $fixtures->taxRule(), $fixtures->currency());

        $this->getService(SiteComponentRepository::class)->save(new SiteComponent(
            'ReferenceShelf',
            'Shelf',
            'A heading above products.',
            [
                ['name' => 'title', 'type' => 'text', 'label' => 'Heading'],
                ['name' => 'category', 'type' => 'reference', 'label' => 'Category', 'record' => 'category'],
                ['name' => 'products', 'type' => 'items', 'label' => 'Products', 'items' => [['name' => 'id', 'type' => 'reference', 'label' => 'Product', 'record' => 'product', 'required' => true]]],
            ],
            ['title' => 'Picked for you', 'category' => null, 'products' => []],
            '<section data-category="{{ props.category }}"><h2>{{ props.title }}</h2>{% for product in props.products %}<span>{{ product.id }}</span>{% endfor %}</section>',
        ), SiteComponentRepository::STATUS_DRAFT);

        $page = $this->createPage('Shelf page', published: false);
        // A screen rendered before the sections are, as the screens of the module expect.
        $this->client->request('GET', '/admin/cms/pages');
        $this->getService(CmsPageWriter::class)->saveContent($page, $this->locale(), new Document([
            new Section('shelf', 'Site:ReferenceShelf', ['title' => 'Picked for you', 'category' => $category->getId(), 'products' => [['id' => $product->getId()]]]),
        ]));

        $form = $this->screen($this->url($page))->filter('[data-testid="cms-section-edit-shelf"] form[data-cms-section-form]');
        $categoryField = $form->filter('input[name="cms_section_shelf[category]"]');
        $productField = $form->filter('input[name="cms_section_shelf[products][0][id]"]');

        self::assertSame(['/admin/cms/partials/sources/categories', '1', (string) $category->getId()], [$categoryField->attr('data-cms-picker'), $categoryField->attr('data-cms-picker-searchable'), $categoryField->attr('value')], 'A category is searched in the list of the categories.');
        self::assertSame(['/admin/cms/partials/sources/products', '1', (string) $product->getId()], [$productField->attr('data-cms-picker'), $productField->attr('data-cms-picker-searchable'), $productField->attr('value')], 'A product of a row is searched in the list of the products.');

        $sent = $form->form();
        $sent['cms_section_shelf[products][0][id]'] = (string) $other->getId();
        self::assertSame(302, $this->send('POST', $sent->getUri(), $sent->getPhpValues()));

        $content = CmsPageContentQuery::create()->filterByPageId($page->getId())->filterByLocale($this->locale())->findOne();
        $props = Document::fromJson($content?->getDraftDocument())?->section('shelf')->props ?? [];
        self::assertSame([$category->getId(), [['id' => $other->getId()]]], [$props['category'] ?? null, $props['products'] ?? null], 'The page stores the ids as numbers.');
    }

    private function url(CmsPage $page): string
    {
        return \sprintf('/admin/cms/pages/%d/content?edit_language_id=%d', $page->getId(), Lang::getDefaultLanguage()->getId());
    }
}
