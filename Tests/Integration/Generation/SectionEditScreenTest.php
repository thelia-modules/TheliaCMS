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

use Thelia\Core\Security\AccessManager;
use Thelia\Model\Lang;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Content\Document;
use TheliaCMS\Content\Section;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Model\CmsBlockContentQuery;
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
    public function testTheFormOfASectionFollowsTheSchemaOfItsComponent(): void
    {
        $page = $this->pageWith([
            new Section('hero', 'Cms:Hero', ['title' => 'Avant', 'heading_level' => '1', 'text' => 'Un texte.', 'cta_label' => 'Voir', 'cta_url' => '/voir']),
            new Section('faq', 'Cms:Questions', ['title' => 'Questions', 'items' => [['question' => 'Combien ?', 'answer' => '<p>Sur devis.</p>']]]),
        ]);

        $screen = $this->screen($this->url($page));
        $hero = $screen->filter('[data-testid="cms-section-edit-hero"] form');

        self::assertSame(1, $hero->count());
        self::assertSame(1, $hero->filter('input[name="_token"]')->count());
        self::assertSame('Avant', $hero->filter('input[name="cms_section_hero[title]"]')->attr('value'));
        self::assertSame(1, $hero->filter('select[name="cms_section_hero[heading_level]"] option[selected][value="1"]')->count(), 'A choice is a select on the shipped choices.');

        $faq = $screen->filter('[data-testid="cms-section-edit-faq"] form');
        self::assertSame(1, $faq->filter('[data-cms-items]')->count());
        self::assertSame(1, $faq->filter('textarea[name="cms_section_faq[items][0][answer]"].wysiwyg')->count(), 'A html prop is a textarea the editor module upgrades.');
        self::assertSame('Combien ?', $faq->filter('input[name="cms_section_faq[items][0][question]"]')->attr('value'));
        self::assertStringContainsString('cms_section_faq[items][__name__][question]', (string) $faq->filter('[data-cms-items]')->attr('data-prototype'), 'The rows are added from the prototype.');
    }

    public function testEditingASectionUpdatesTheDraftAndTheLedger(): void
    {
        $page = $this->pageWith([
            new Section('hero', 'Cms:Hero', ['title' => 'Avant', 'heading_level' => '1', 'text' => 'Un texte.']),
            new Section('faq', 'Cms:Questions', ['title' => 'Questions', 'items' => [['question' => 'Combien ?', 'answer' => '<p>Sur devis.</p>']]]),
        ]);

        $form = $this->screen($this->url($page))->filter('[data-testid="cms-section-edit-hero"] form')->form();
        $form['cms_section_hero[title]'] = 'Après';
        $form['cms_section_hero[text]'] = '<p>Un texte <strong>corrigé</strong>.</p>';

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        $content = CmsPageContentQuery::create()->filterByPageId($page->getId())->filterByLocale($this->locale())->findOne();
        $document = Document::fromJson($content?->getDraftDocument());

        self::assertSame('Après', $document?->section('hero')?->props['title']);
        self::assertSame('<p>Un texte <strong>corrigé</strong>.</p>', $document?->section('hero')?->props['text']);
        self::assertSame('Combien ?', $document?->section('faq')?->props['items'][0]['question'], 'The other sections are kept as they are.');
        self::assertStringContainsString('Après', (string) $content?->getDraftHtml(), 'The draft is rendered again.');
        self::assertNull($content?->getPublishedHtml());

        $row = CmsPageGenerationQuery::create()->filterByTargetId($page->getId())->orderById(\Propel\Runtime\ActiveQuery\Criteria::DESC)->findOne();
        self::assertSame('manual', $row?->getKind());
        self::assertSame('done', $row?->getStatus());
        self::assertSame('0.000000', $row?->getCostUsd());
        self::assertStringContainsString('"title":"Après"', (string) $row?->getDocument());

        $screen = $this->screen($this->url($page));
        self::assertSame(1, $screen->filter('[data-testid="cms-builder-flash-success"]')->count());
        self::assertStringContainsString('Après', $screen->filter('[data-testid="cms-content-sections"] li[data-section="hero"]')->text());
    }

    public function testRowsOfAListAreAddedAndRemoved(): void
    {
        $page = $this->pageWith([
            new Section('faq', 'Cms:Questions', ['title' => 'Questions', 'items' => [['question' => 'Un ?', 'answer' => '<p>1</p>'], ['question' => 'Deux ?', 'answer' => '<p>2</p>']]]),
        ]);

        $form = $this->screen($this->url($page))->filter('[data-testid="cms-section-edit-faq"] form')->form();
        $values = $form->getPhpValues();
        // The second row dropped, a third one typed as the script would add it.
        unset($values['cms_section_faq']['items'][1]);
        $values['cms_section_faq']['items'][2] = ['question' => 'Trois ?', 'answer' => '<p>3</p>'];

        self::assertSame(302, $this->send('POST', $form->getUri(), $values));

        $document = Document::fromJson(CmsPageContentQuery::create()->filterByPageId($page->getId())->findOne()?->getDraftDocument());
        self::assertSame(['Un ?', 'Trois ?'], array_column($document?->section('faq')?->props['items'] ?? [], 'question'));
    }

    public function testTheSchemaStillRefusesWhatItRefusesToTheModel(): void
    {
        $page = $this->pageWith([
            new Section('hero', 'Cms:Hero', ['title' => 'Avant', 'heading_level' => '1', 'cta_label' => 'Voir', 'cta_url' => '/voir']),
        ]);

        $form = $this->screen($this->url($page))->filter('[data-testid="cms-section-edit-hero"] form')->form();
        $form['cms_section_hero[cta_url]'] = 'javascript:alert(1)';

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        $document = Document::fromJson(CmsPageContentQuery::create()->filterByPageId($page->getId())->findOne()?->getDraftDocument());
        self::assertSame('/voir', $document?->section('hero')?->props['cta_url'], 'Nothing was changed.');
        self::assertSame(0, CmsPageGenerationQuery::create()->filterByTargetId($page->getId())->count(), 'Nothing was written to the ledger.');

        $screen = $this->screen($this->url($page));
        self::assertStringContainsString('cta_url', $screen->filter('[data-testid="cms-builder-flash-danger"]')->text());
    }

    public function testASectionNoLongerThereIsRefused(): void
    {
        $page = $this->pageWith([new Section('hero', 'Cms:Hero', ['title' => 'Avant', 'heading_level' => '1'])]);
        $token = $this->tokenOfTheScripts($this->screen($this->url($page)));

        self::assertSame(302, $this->send('POST', \sprintf('/admin/cms/pages/%d/content/update', $page->getId()), ['_token' => $token, 'cms_section_gone' => ['section' => 'gone', 'title' => 'X']]));
        self::assertSame(1, $this->screen($this->url($page))->filter('[data-testid="cms-builder-flash-danger"]')->count());
    }

    public function testEditingByHandTakesTheRightToChangePagesNotTheGenerator(): void
    {
        $page = $this->pageWith([new Section('hero', 'Cms:Hero', ['title' => 'Avant', 'heading_level' => '1'])]);

        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([
            CmsResources::PAGE => [AccessManager::VIEW, AccessManager::UPDATE],
        ]));
        $form = $this->screen($this->url($page))->filter('[data-testid="cms-section-edit-hero"] form')->form();
        $form['cms_section_hero[title]'] = 'Sans générateur';
        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));
        self::assertStringContainsString('Sans générateur', (string) CmsPageContentQuery::create()->filterByPageId($page->getId())->findOne()?->getDraftHtml());

        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([
            CmsResources::PAGE => [AccessManager::VIEW],
        ]));
        $screen = $this->screen($this->url($page));
        self::assertSame(0, $screen->filter('[data-testid="cms-section-edit-hero"]')->count(), 'Reading the screen is not the right to change a section.');
        self::assertSame(403, $this->send('POST', \sprintf('/admin/cms/pages/%d/content/update', $page->getId()), ['_token' => $this->tokenOfTheScripts($screen), 'cms_section_hero' => ['section' => 'hero', 'title' => 'Refused']]));
    }

    public function testABlockIsEditedTheSameWay(): void
    {
        $block = (new CmsBlock())->setCode('edited-block');
        $block->setLocale($this->locale())->setTitle('Edited');
        $block->save();
        $this->renderedOnce();
        $this->getService(\TheliaCMS\Block\Admin\CmsBlockWriter::class)->saveContent($block, $this->locale(), new Document([
            new Section('figures', 'Cms:Figures', ['title' => 'En chiffres', 'items' => [['value' => '48 h', 'label' => 'Livraison']]]),
        ]));
        $url = \sprintf('/admin/cms/blocks/%d/content?edit_language_id=%d', $block->getId(), Lang::getDefaultLanguage()->getId());

        $form = $this->screen($url)->filter('[data-testid="cms-section-edit-figures"] form')->form();
        $form['cms_section_figures[items][0][value]'] = '24 h';

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));
        self::assertStringContainsString('24 h', (string) CmsBlockContentQuery::create()->filterByBlockId($block->getId())->findOne()?->getDraftHtml());
        self::assertSame('manual', CmsPageGenerationQuery::create()->filterByTargetType('block')->filterByTargetId($block->getId())->findOne()?->getKind());
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
