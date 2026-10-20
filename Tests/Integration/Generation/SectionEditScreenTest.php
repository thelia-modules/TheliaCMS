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

namespace TheliaCMS\Tests\Integration\Generation;

use Symfony\Component\DomCrawler\Crawler;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\Lang;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Content\Document;
use TheliaCMS\Content\Section;
use TheliaCMS\Content\SectionCatalog;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageGenerationQuery;
use TheliaCMS\Page\Admin\CmsPageWriter;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * A section changed by hand on the content screen: a form read from the
 * schema of its component, the document and the draft updated, a row of the
 * ledger, and the schema still the judge of what comes in.
 */
final class SectionEditScreenTest extends AdminScreenTestCase
{
    /** A component of the Flexy theme holding a list of products, opened to pages by the theme. */
    private const string LIST_COMPONENT = 'Layouts:CrossSelling:Base';

    public function testTheFormOfASectionFollowsTheSchemaOfItsComponent(): void
    {
        $page = $this->pageWith([
            new Section('intro', 'cms:richtext', ['html' => '<h1>Avant</h1><p>Un texte.</p>']),
            new Section('news', 'Partial:latest-contents', ['count' => 3, 'folder' => null, 'heading' => 'Actualités']),
        ]);

        $screen = $this->screen($this->url($page));
        $intro = $screen->filter('[data-testid="cms-section-edit-intro"] form');

        self::assertSame(1, $intro->count());
        self::assertSame(1, $intro->filter('input[name="_token"]')->count());
        self::assertSame(1, $intro->filter('textarea[name="cms_section_intro[html]"].wysiwyg')->count(), 'A html prop is a textarea the editor module upgrades.');

        $news = $screen->filter('[data-testid="cms-section-edit-news"] form');
        self::assertSame('Actualités', $news->filter('input[name="cms_section_news[heading]"]')->attr('value'));
        self::assertSame('3', $news->filter('input[name="cms_section_news[count]"]')->attr('value'));
    }

    public function testEditingASectionUpdatesTheDraftAndTheLedger(): void
    {
        $page = $this->pageWith([
            new Section('intro', 'cms:richtext', ['html' => '<h1>Avant</h1><p>Un texte.</p>']),
            new Section('news', 'Partial:latest-contents', ['count' => 3, 'folder' => null, 'heading' => 'Actualités']),
        ]);

        $form = $this->screen($this->url($page))->filter('[data-testid="cms-section-edit-intro"] form')->form();
        $form['cms_section_intro[html]'] = '<h1>Après</h1><p>Un texte <strong>corrigé</strong>.</p>';

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        $content = CmsPageContentQuery::create()->filterByPageId($page->getId())->filterByLocale($this->locale())->findOne();
        $document = Document::fromJson($content?->getDraftDocument());

        self::assertSame('<h1>Après</h1><p>Un texte <strong>corrigé</strong>.</p>', $document?->section('intro')?->props['html']);
        self::assertSame('Actualités', $document?->section('news')?->props['heading'], 'The other sections are kept as they are.');
        self::assertStringContainsString('Après', (string) $content?->getDraftHtml(), 'The draft is rendered again.');
        self::assertNull($content?->getPublishedHtml());

        $row = CmsPageGenerationQuery::create()->filterByTargetId($page->getId())->orderById(\Propel\Runtime\ActiveQuery\Criteria::DESC)->findOne();
        self::assertSame('manual', $row?->getKind());
        self::assertSame('done', $row?->getStatus());
        self::assertSame('0.000000', $row?->getCostUsd());
        self::assertStringContainsString('<h1>Après</h1>', (string) $row?->getDocument());

        $screen = $this->screen($this->url($page));
        self::assertSame(1, $screen->filter('[data-testid="cms-builder-flash-success"]')->count());
        self::assertStringContainsString('Après', $screen->filter('[data-testid="cms-content-sections"] li[data-section="intro"]')->text());
    }

    public function testASectionIsRemovedByHandAndTheLedgerSaysSo(): void
    {
        $page = $this->pageWith([
            new Section('intro', 'cms:richtext', ['html' => '<h1>Avant</h1><p>Un texte.</p>']),
            new Section('faq', 'cms:richtext', ['html' => '<h2>Questions</h2><p>Combien ? Sur devis.</p>']),
        ]);

        $screen = $this->screen($this->url($page));
        self::assertSame(['1', '2'], $screen->filter('[data-testid="cms-content-sections"] .cms-content__item-rank')->each(static fn (Crawler $rank): string => trim($rank->text())), 'The sections are numbered, as they are in the preview.');
        $form = $screen->filter('[data-testid="cms-section-remove-faq"]')->form();

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        $content = CmsPageContentQuery::create()->filterByPageId($page->getId())->filterByLocale($this->locale())->findOne();
        $document = Document::fromJson($content?->getDraftDocument());

        self::assertSame(['intro'], array_map(static fn (Section $section): string => $section->id, $document?->sections ?? []));
        self::assertStringNotContainsString('Combien ?', (string) $content?->getDraftHtml(), 'The draft is rendered again without it.');
        self::assertStringContainsString('data-cms-section="intro"', (string) $content?->getDraftHtml(), 'The rendered section carries its id, for the preview to point at it.');

        $row = CmsPageGenerationQuery::create()->filterByTargetId($page->getId())->orderById(\Propel\Runtime\ActiveQuery\Criteria::DESC)->findOne();
        self::assertSame('manual', $row?->getKind());
        self::assertStringContainsString('removed', (string) $row?->getPrompt());

        $screen = $this->screen($this->url($page));
        self::assertSame(1, $screen->filter('[data-testid="cms-builder-flash-success"]')->count());
        self::assertSame(1, $screen->filter('[data-testid="cms-content-sections"] li[data-section]')->count());

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()), 'Removing it again is refused, not an error.');
        self::assertSame(1, $this->screen($this->url($page))->filter('[data-testid="cms-builder-flash-danger"]')->count());
    }

    public function testRowsOfAListAreAddedAndRemoved(): void
    {
        if (null === $this->getService(SectionCatalog::class)->find(self::LIST_COMPONENT)) {
            self::markTestSkipped('The theme opens no component holding a list to pages.');
        }

        $factory = new FixtureFactory($this->getPropelConnection());
        [$one, $two, $three] = array_map(
            static fn (int $rank): int => (int) $factory->product($factory->category(), $factory->taxRule(), $factory->currency())->getId(),
            [1, 2, 3],
        );
        // Stored as it is rather than through the writer: the component reads
        // its products with the request of a screen, and there is none here.
        $page = $this->createPage('Edited by hand', published: false);
        CmsPageContentQuery::create()->filterByPageId($page->getId())->filterByLocale($this->locale())->findOneOrCreate()
            ->setDraftDocument((new Document([
                new Section('row', self::LIST_COMPONENT, ['title' => 'Nos produits', 'product_ids' => [['id' => $one], ['id' => $two]]]),
            ]))->toJson())
            ->save();

        $screen = $this->screen($this->url($page));
        $list = $screen->filter('[data-testid="cms-section-edit-row"] [data-cms-items]');
        self::assertSame(1, $list->count());
        self::assertStringContainsString('cms_section_row[product_ids][__name__]', (string) $list->attr('data-prototype'), 'The rows are added from the prototype.');

        $form = $screen->filter('[data-testid="cms-section-edit-row"] form')->form();
        $values = $form->getPhpValues();
        // The second row dropped, a third one picked as the script would add it.
        unset($values['cms_section_row']['product_ids'][1]);
        $values['cms_section_row']['product_ids'][2] = ['id' => (string) $three];

        self::assertSame(302, $this->send('POST', $form->getUri(), $values));

        $document = Document::fromJson(CmsPageContentQuery::create()->filterByPageId($page->getId())->findOne()?->getDraftDocument());
        self::assertSame([$one, $three], array_column($document?->section('row')?->props['product_ids'] ?? [], 'id'));
    }

    public function testTheSchemaStillRefusesWhatItRefusesToTheModel(): void
    {
        $page = $this->pageWith([
            new Section('news', 'Partial:latest-contents', ['count' => 3, 'folder' => null, 'heading' => 'Actualités']),
        ]);

        $form = $this->screen($this->url($page))->filter('[data-testid="cms-section-edit-news"] form')->form();
        $form['cms_section_news[count]'] = '99';

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        $document = Document::fromJson(CmsPageContentQuery::create()->filterByPageId($page->getId())->findOne()?->getDraftDocument());
        self::assertSame(3, $document?->section('news')?->props['count'], 'Nothing was changed.');
        self::assertSame(0, CmsPageGenerationQuery::create()->filterByTargetId($page->getId())->count(), 'Nothing was written to the ledger.');

        $screen = $this->screen($this->url($page));
        self::assertStringContainsString('count', $screen->filter('[data-testid="cms-builder-flash-danger"]')->text());
    }

    public function testASectionNoLongerThereIsRefused(): void
    {
        $page = $this->pageWith([new Section('intro', 'cms:richtext', ['html' => '<h1>Avant</h1>'])]);
        $token = $this->tokenOfTheScripts($this->screen($this->url($page)));

        self::assertSame(302, $this->send('POST', \sprintf('/admin/cms/pages/%d/content/update', $page->getId()), ['_token' => $token, 'cms_section_gone' => ['section' => 'gone', 'html' => 'X']]));
        self::assertSame(1, $this->screen($this->url($page))->filter('[data-testid="cms-builder-flash-danger"]')->count());
    }

    public function testEditingByHandTakesTheRightToChangePagesNotTheGenerator(): void
    {
        $page = $this->pageWith([new Section('intro', 'cms:richtext', ['html' => '<h1>Avant</h1>'])]);

        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([
            CmsResources::PAGE => [AccessManager::VIEW, AccessManager::UPDATE],
        ]));
        $form = $this->screen($this->url($page))->filter('[data-testid="cms-section-edit-intro"] form')->form();
        $form['cms_section_intro[html]'] = '<h1>Sans générateur</h1>';
        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));
        self::assertStringContainsString('Sans générateur', (string) CmsPageContentQuery::create()->filterByPageId($page->getId())->findOne()?->getDraftHtml());

        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([
            CmsResources::PAGE => [AccessManager::VIEW],
        ]));
        $screen = $this->screen($this->url($page));
        self::assertSame(0, $screen->filter('[data-testid="cms-section-edit-intro"]')->count(), 'Reading the screen is not the right to change a section.');
        self::assertSame(403, $this->send('POST', \sprintf('/admin/cms/pages/%d/content/update', $page->getId()), ['_token' => $this->tokenOfTheScripts($screen), 'cms_section_intro' => ['section' => 'intro', 'html' => 'Refused']]));
    }

    /**
     * @param list<Section> $sections
     */
    private function pageWith(array $sections): CmsPage
    {
        $page = $this->createPage('Edited by hand', published: false);
        $this->renderedOnce();
        $this->getService(CmsPageWriter::class)->saveContent($page, $this->locale(), new Document($sections));

        return $page;
    }

    /**
     * A screen rendered before the sections are: the parser of Thelia sets
     * the `app` global of Twig at the first render of the process, with the
     * request of the moment — none, when the test renders a section before it
     * asks for a screen — and the screens then read `app.request` as null.
     */
    private function renderedOnce(): void
    {
        $this->client->request('GET', '/admin/cms/pages');
    }

    private function url(CmsPage $page): string
    {
        return \sprintf('/admin/cms/pages/%d/content?edit_language_id=%d', $page->getId(), Lang::getDefaultLanguage()->getId());
    }
}
