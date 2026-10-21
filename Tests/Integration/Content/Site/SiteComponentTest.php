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

namespace TheliaCMS\Tests\Integration\Content\Site;

use TheliaCMS\Content\Document;
use TheliaCMS\Content\DocumentValidator;
use TheliaCMS\Content\InvalidDocumentException;
use TheliaCMS\Content\Section;
use TheliaCMS\Content\SectionCatalog;
use TheliaCMS\Content\Site\InvalidSiteComponentException;
use TheliaCMS\Content\Site\SiteComponent;
use TheliaCMS\Content\Site\SiteComponentRepository;
use TheliaCMS\Content\Site\SiteComponentSection;
use TheliaCMS\Content\Site\SiteComponentValidator;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\Tests\Unit\Content\Site\PriceTable;

/**
 * A component the site made and kept is a section like any other: in the
 * catalogue, checked in a document, drawn on the page and kept by the
 * publication.
 */
final class SiteComponentTest extends CmsIntegrationTestCase
{
    public function testAKeptComponentIsInTheCatalogueAndADraftIsNot(): void
    {
        $repository = $this->getService(SiteComponentRepository::class);
        $repository->save((new SiteComponentValidator())->validate(PriceTable::component()), SiteComponentRepository::STATUS_READY);
        $draft = PriceTable::component();
        $repository->save(new SiteComponent('PriceGrid', $draft->label, $draft->description, $draft->props, $draft->sample, $draft->template), SiteComponentRepository::STATUS_DRAFT);

        $catalog = $this->getService(SectionCatalog::class);

        self::assertInstanceOf(SiteComponentSection::class, $catalog->find('Site:PriceTable'));
        self::assertArrayHasKey('Site:PriceTable', $catalog->offered(), 'Offered to the generator like any other component.');
        self::assertNull($catalog->find('Site:PriceGrid'), 'A draft is still being looked at.');
    }

    public function testAPageHoldingTheComponentIsCheckedDrawnAndPublished(): void
    {
        $this->getService(SiteComponentRepository::class)->save((new SiteComponentValidator())->validate(PriceTable::component(css: '.plan { color: var(--color-primary); }')), SiteComponentRepository::STATUS_READY);
        $page = $this->createPage('Tarifs', published: false);

        try {
            $this->getService(DocumentValidator::class)->validate(new Document([new Section('plans', 'Site:PriceTable', ['plans' => [['price' => '9 €']]])]));
            self::fail('A document is checked against the props of the component.');
        } catch (InvalidDocumentException $exception) {
            self::assertStringContainsString('name', implode(' ', $exception->problems));
        }

        $this->writer()->saveContent($page, $this->locale(), new Document([
            new Section('intro', 'cms:richtext', ['html' => '<h1>Tarifs</h1>']),
            new Section('plans', 'Site:PriceTable', ['title' => 'Nos formules', 'plans' => [['name' => 'Studio', 'price' => '40 €', 'details' => '<p>Une heure</p><script>alert(1)</script>']]]),
        ]));
        $this->writer()->publish($page, $this->locale());

        $content = CmsPageContentQuery::create()->filterByPageId($page->getId())->filterByLocale($this->locale())->findOne();
        self::assertNotNull($content);
        $published = (string) $content->getPublishedHtml();

        self::assertStringContainsString('cms-site--price-table', (string) $content->getDraftHtml());
        self::assertStringContainsString('data-cms-section="plans"', $published);
        self::assertStringContainsString('<h3>Studio</h3>', $published);
        self::assertStringContainsString('cms-site cms-site--price-table', $published, 'The class the stylesheet is scoped to survives the publication.');
        self::assertStringNotContainsString('<script', $published, 'What a markup prop holds is filtered like any section.');
    }

    public function testTheCodeOfANewComponentIsFreeAndNeverChanges(): void
    {
        $repository = $this->getService(SiteComponentRepository::class);
        $component = (new SiteComponentValidator())->validate(PriceTable::component());
        $repository->save($component, SiteComponentRepository::STATUS_DRAFT);

        try {
            $repository->save($component, SiteComponentRepository::STATUS_DRAFT);
            self::fail('A new component takes a free code.');
        } catch (InvalidSiteComponentException $exception) {
            self::assertStringContainsString('already', $exception->getMessage());
        }

        $repository->save($component, SiteComponentRepository::STATUS_READY, replacing: 'PriceTable');
        self::assertSame(SiteComponentRepository::STATUS_READY, $repository->status('PriceTable'));
        self::assertSame($component->sample, $repository->find('PriceTable')?->sample, 'What is kept is read back as it was.');
    }
}
