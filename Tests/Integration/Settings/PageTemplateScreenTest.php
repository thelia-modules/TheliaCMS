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

namespace TheliaCMS\Tests\Integration\Settings;

use TheliaCMS\Content\Document;
use TheliaCMS\Generation\Brief;
use TheliaCMS\Generation\ClaudeClientProviderInterface;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Model\Map\CmsPageTableMap;
use TheliaCMS\Page\Admin\CmsPageWriter;
use TheliaCMS\Page\Field\PageFields;
use TheliaCMS\Page\PageTypeRepository;
use TheliaCMS\Page\PageTypeWriter;
use TheliaCMS\Tests\Integration\Generation\FakeClaudeTransport;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * A page type as a template, through the screens: describing it, adding its
 * fields, creating its model, starting a page from it, filling the fields of
 * a page, and the generator writing them.
 */
final class PageTemplateScreenTest extends AdminScreenTestCase
{
    private const string TYPE = 'recipe-screen';

    private FakeClaudeTransport $api;

    protected function setUp(): void
    {
        parent::setUp();

        $this->api = new FakeClaudeTransport();
        $provider = new class($this->api) implements ClaudeClientProviderInterface {
            public function __construct(public ClaudeClientProviderInterface $delegate)
            {
            }

            public function client(): \Anthropic\Client
            {
                return $this->delegate->client();
            }
        };
        static::getContainer()->set(ClaudeClientProviderInterface::class, $provider);

        $this->typeWriter()->add(self::TYPE);
    }

    public function testTheTypeIsNamedAndBriefedOnItsScreen(): void
    {
        $form = $this->screen($this->typeUrl())->filter('[data-testid="cms-page-type-save"]')->form();
        $form['page_type_edit[title]'] = 'Recette';
        $form['page_type_edit[briefText]'] = 'Une recette de saison, pas à pas.';
        $form['page_type_edit[briefTone]'] = 'chaleureux';

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        $details = $this->types()->details(self::TYPE);
        self::assertSame('Recette', $details->title($this->locale()));
        self::assertSame('Une recette de saison, pas à pas.', $details->brief()?->text);
        self::assertSame('chaleureux', $details->brief()?->tone);

        $list = $this->screen('/admin/cms/settings/page-types');
        self::assertStringContainsString('Recette', $list->filter('[data-testid="cms-page-type-edit-'.self::TYPE.'"]')->text());
        self::assertStringContainsString('Brief', $list->filter('[data-testid="cms-page-type-template-of-'.self::TYPE.'"]')->text());
    }

    public function testAFieldIsAddedThenEdited(): void
    {
        $form = $this->screen($this->typeUrl())->filter('[data-testid="cms-page-type-field-submit"]')->form();
        $form['page_type_field[kind]'] = 'integer';
        $form['page_type_field[label]'] = 'Personnes';
        $form['page_type_field[code]'] = 'servings';
        $form['page_type_field[min]'] = '1';
        $form['page_type_field[required]']->tick();

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        $field = $this->types()->details(self::TYPE)->fields()->find('servings');
        self::assertNotNull($field);
        self::assertTrue($field->required);
        self::assertSame(1, $field->min);

        $screen = $this->screen($this->typeUrl().'/fields/servings');
        self::assertSame(1, $screen->filter('[data-testid="cms-page-type-field-servings"]')->count());
        self::assertNotNull($screen->filter('input[name="page_type_field[code]"]')->attr('disabled'), 'The code of a field never changes.');

        $form = $screen->filter('[data-testid="cms-page-type-field-submit"]')->form();
        $form['page_type_field[label]'] = 'Couverts';

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));
        self::assertSame('Couverts', $this->types()->details(self::TYPE)->fields()->find('servings')?->label($this->locale()));
    }

    public function testAFieldThatDoesNotHoldTogetherIsRefusedOnTheForm(): void
    {
        $form = $this->screen($this->typeUrl())->filter('[data-testid="cms-page-type-field-submit"]')->form();
        $form['page_type_field[kind]'] = 'choice';
        $form['page_type_field[label]'] = 'Difficulté';
        $form['page_type_field[code]'] = 'difficulty';

        self::assertSame(200, $this->send('POST', $form->getUri(), $form->getPhpValues()));
        self::assertTrue($this->types()->details(self::TYPE)->fields()->isEmpty());
        self::assertStringContainsString('lists the words it takes', $this->client->getResponse()->getContent() ?: '');
    }

    public function testTheModelIsCreatedAndOpensOnItsContent(): void
    {
        $action = $this->typeUrl().'/model';
        $token = $this->tokenOfTheForm($this->screen($this->typeUrl()), $action);

        self::assertSame(302, $this->send('POST', $action, ['_token' => $token]));

        $model = $this->types()->modelOf(self::TYPE);
        self::assertNotNull($model);
        self::assertStringContainsString(\sprintf('/admin/cms/pages/%d/content', $model->getId()), (string) $this->client->getResponse()->headers->get('Location'));
        self::assertSame(0, $this->screen(\sprintf('/admin/cms/pages/%d/content', $model->getId()))->filter('[data-testid="cms-content-publish"]')->count(), 'The content of a model offers no publication.');

        $edit = $this->screen(\sprintf('/admin/cms/pages/%d', $model->getId()));
        self::assertSame(1, $edit->filter('[data-testid="cms-page-model-badge"]')->count());
        self::assertSame(0, $edit->filter('form[action*="/publish"]')->count(), 'A model offers no publication.');
        self::assertNotNull($edit->filter('select[name="cms_page[pageType]"]')->attr('disabled'), 'The model stays the model of its type.');
        self::assertSame(0, $this->screen('/admin/cms/pages')->filter(\sprintf('[data-testid="cms-page-row-%d"]', $model->getId()))->count(), 'A model is not listed with the pages.');
    }

    public function testAPageIsStartedFromTheTemplateUnderItsOwnTitle(): void
    {
        $this->typeWriter()->saveField(self::TYPE, null, $this->locale(), ['code' => 'servings', 'kind' => 'integer', 'label' => 'Personnes']);
        $model = $this->writer()->createModel(self::TYPE, $this->locale(), 'Recette (modèle)');
        $this->writer()->saveContent($model, $this->locale(), Document::richText('<h1>Recette</h1><p>Le modèle de la recette.</p>'));
        $this->writer()->saveFields($model, $this->locale(), ['servings' => 4]);

        $list = $this->screen('/admin/cms/pages');
        $form = $list->filter('[data-testid="cms-pages-from-type"]')->form();
        $form['page_type'] = self::TYPE;
        $form['title'] = 'Tarte aux pommes';

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        $page = CmsPageQuery::create()->filterByPageType(self::TYPE)->filterByModel(0)->findOne();
        self::assertNotNull($page);
        self::assertSame('Tarte aux pommes', $page->setLocale($this->locale())->getTitle());
        self::assertStringContainsString('Le modèle de la recette', (string) CmsPageContentQuery::create()->filterByPageId($page->getId())->findOne()?->getDraftHtml());
        self::assertSame(4, $this->fields()->valuesOf($page, $this->locale())['servings']);
    }

    public function testTheFieldsOfAPageAreFilledOnItsScreen(): void
    {
        $this->typeWriter()->saveField(self::TYPE, null, $this->locale(), ['code' => 'servings', 'kind' => 'integer', 'label' => 'Personnes', 'required' => true]);
        $this->typeWriter()->saveField(self::TYPE, null, $this->locale(), ['code' => 'vegetarian', 'kind' => 'boolean', 'label' => 'Végétarien']);
        $page = $this->createPage('Gratin', published: false);
        $page->setPageType(self::TYPE)->save();

        $screen = $this->screen(\sprintf('/admin/cms/pages/%d', $page->getId()));
        self::assertSame(1, $screen->filter('[data-testid="cms-page-tab-fields"]')->count());

        $form = $screen->filter('form[name="cms_page"]')->form();
        $form['cms_page[fields][servings]'] = '6';
        $form['cms_page[fields][vegetarian]']->tick();

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        CmsPageTableMap::clearInstancePool();
        self::assertSame(['servings' => 6, 'vegetarian' => true], $this->fields()->valuesOf(CmsPageQuery::create()->findPk($page->getId()), $this->locale()));
    }

    public function testAPublicationWithoutARequiredFieldIsRefusedAndSaid(): void
    {
        $this->typeWriter()->saveField(self::TYPE, null, $this->locale(), ['code' => 'servings', 'kind' => 'integer', 'label' => 'Personnes', 'required' => true]);
        $page = $this->createPage('Gratin', published: false);
        $page->setPageType(self::TYPE)->save();

        $action = \sprintf('/admin/cms/pages/%d/content/publish', $page->getId());
        $token = $this->tokenOfTheForm($this->screen(\sprintf('/admin/cms/pages/%d/content', $page->getId())), $action);

        self::assertSame(302, $this->send('POST', $action, ['_token' => $token]));
        self::assertStringContainsString('Personnes', $this->client->followRedirect()->filter('[role="alert"], .alert')->text(''));
        self::assertNull(CmsPageContentQuery::create()->filterByPageId($page->getId())->findOne()?->getPublishedAt());
    }

    public function testABriefOfAPageOfTheTypeWritesItsFields(): void
    {
        $this->typeWriter()->describe(self::TYPE, $this->locale(), 'Recette', null, new Brief('Une recette de saison, pas à pas.'));
        $this->typeWriter()->saveField(self::TYPE, null, $this->locale(), ['code' => 'servings', 'kind' => 'integer', 'label' => 'Personnes', 'required' => true]);
        $this->typeWriter()->saveField(self::TYPE, null, $this->locale(), ['code' => 'cooking_time', 'kind' => 'duration', 'label' => 'Cuisson']);
        $page = $this->createPage('Tarte aux pommes', published: false);
        $page->setPageType(self::TYPE)->save();

        $this->api->answer(FakeClaudeTransport::message([FakeClaudeTransport::text(json_encode([
            'version' => 1,
            'sections' => [['id' => 'recipe', 'component' => 'cms:richtext', 'props' => json_encode(['html' => '<h1>Tarte aux pommes</h1><p>Six parts.</p>'], \JSON_THROW_ON_ERROR)]],
            'fields' => json_encode(['servings' => 6, 'cooking_time' => 45], \JSON_THROW_ON_ERROR),
        ], \JSON_THROW_ON_ERROR))]));

        $url = \sprintf('/admin/cms/pages/%d/content', $page->getId());
        $form = $this->screen($url)->filter('[data-testid="cms-content-brief"] form')->form();
        $form['cms_brief[text]'] = 'La tarte aux pommes de grand-mère.';

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        $request = $this->api->requests[0];
        self::assertContains('fields', $request['output_config']['format']['schema']['required']);
        self::assertStringContainsString('# The type of the page: Recette', $request['messages'][0]['content']);
        self::assertStringContainsString('Une recette de saison, pas à pas.', $request['messages'][0]['content']);
        self::assertStringContainsString('- `servings`: Personnes (a whole number, required)', $request['messages'][0]['content']);

        CmsPageTableMap::clearInstancePool();
        self::assertSame(['servings' => 6, 'cooking_time' => 45], $this->fields()->valuesOf(CmsPageQuery::create()->findPk($page->getId()), $this->locale()));
        self::assertStringContainsString('Six parts', (string) CmsPageContentQuery::create()->filterByPageId($page->getId())->findOne()?->getDraftHtml());
        self::assertSame(1, $this->screen($url)->filter('[data-testid="cms-content-type-brief"]')->count(), 'The screen says the brief of the type is read first.');
    }

    private function typeUrl(): string
    {
        return '/admin/cms/settings/page-types/'.self::TYPE;
    }

    private function types(): PageTypeRepository
    {
        return $this->getService(PageTypeRepository::class);
    }

    private function typeWriter(): PageTypeWriter
    {
        return $this->getService(PageTypeWriter::class);
    }

    private function writer(): CmsPageWriter
    {
        return $this->getService(CmsPageWriter::class);
    }

    private function fields(): PageFields
    {
        return $this->getService(PageFields::class);
    }
}
