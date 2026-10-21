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

use Psr\Log\NullLogger;
use TheliaCMS\Content\Document;
use TheliaCMS\Content\DocumentSchema;
use TheliaCMS\Content\DocumentValidator;
use TheliaCMS\Generation\Brief;
use TheliaCMS\Generation\GenerationLog;
use TheliaCMS\Generation\GenerationRequest;
use TheliaCMS\Generation\GenerationTargets;
use TheliaCMS\Generation\GeneratorSettings;
use TheliaCMS\Generation\PageGenerator;
use TheliaCMS\Generation\Pricing;
use TheliaCMS\Generation\PromptBuilder;
use TheliaCMS\Generation\Tool\GeneratorTools;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Page\Admin\PageDraft;
use TheliaCMS\Page\PageTypeWriter;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * The generator for a page whose type is a template: it reads the brief of
 * the type, follows its model, and writes the fields of the page in the
 * envelope, corrected like the document when they are wrong.
 */
final class TypedPageGenerationTest extends CmsIntegrationTestCase
{
    private FakeClaudeTransport $api;

    protected function setUp(): void
    {
        parent::setUp();

        $this->api = new FakeClaudeTransport();

        $writer = $this->getService(PageTypeWriter::class);
        $writer->add('recipe-generated');
        $writer->describe('recipe-generated', $this->locale(), 'Recette', null, new Brief('Une recette de saison, pas à pas.', audience: 'des cuisiniers pressés'));
        $writer->saveField('recipe-generated', null, $this->locale(), ['code' => 'servings', 'kind' => 'integer', 'label' => 'Personnes', 'required' => true, 'min' => 1]);
        $writer->saveField('recipe-generated', null, $this->locale(), ['code' => 'tip', 'kind' => 'text', 'label' => 'Astuce', 'translated' => true]);
    }

    public function testABriefFollowsTheModelAndWritesTheFields(): void
    {
        $model = $this->writer()->createModel('recipe-generated', $this->locale(), 'Recette (modèle)');
        $this->writer()->saveContent($model, $this->locale(), Document::richText('<h1>Recette</h1><p>Le modèle de la recette.</p>'));
        $page = $this->givenARecipe('Tarte aux pommes');

        $this->api->answer(self::answer(['servings' => 6, 'tip' => 'Servir tiède.']));

        $result = $this->generator()->generate(GenerationRequest::fromBrief($this->targets()->forPage($page, $this->locale()), $this->locale(), new Brief('La tarte de grand-mère.')));

        self::assertSame(['servings' => 6, 'tip' => 'Servir tiède.'], $result->fields);

        $message = $this->api->requests[0]['messages'][0]['content'];
        self::assertStringContainsString('# The type of the page: Recette', $message);
        self::assertStringContainsString('Une recette de saison, pas à pas.', $message);
        self::assertStringContainsString('Audience: des cuisiniers pressés', $message, 'The audience of the type stands when the page names none.');
        self::assertStringContainsString('Le modèle de la recette', $message, 'The model of the type is followed.');
        self::assertStringContainsString('- `tip`: Astuce (one line of plain text)', $message);
    }

    public function testWrongFieldsGoBackToTheModelWithTheDocument(): void
    {
        $page = $this->givenARecipe('Tarte aux pommes');

        $this->api
            ->answer(self::answer(['servings' => 'six']))
            ->answer(self::answer(['servings' => 6]));

        $result = $this->generator()->generate(GenerationRequest::fromBrief($this->targets()->forPage($page, $this->locale()), $this->locale(), new Brief('La tarte de grand-mère.')));

        self::assertSame(['servings' => 6], $result->fields);
        self::assertCount(2, $this->api->requests);
        self::assertStringContainsString('Field Personnes: The value is not a whole number.', (string) json_encode($this->api->requests[1]['messages'][2]['content'], \JSON_UNESCAPED_UNICODE));
    }

    public function testARequiredFieldTheAnswerLeavesEmptyIsAskedAgain(): void
    {
        $page = $this->givenARecipe('Tarte aux pommes');

        $this->api
            ->answer(self::answer(['tip' => 'Servir tiède.']))
            ->answer(self::answer(['servings' => 4]));

        $this->generator()->generate(GenerationRequest::fromBrief($this->targets()->forPage($page, $this->locale()), $this->locale(), new Brief('La tarte de grand-mère.')));

        self::assertStringContainsString('The field `servings` is required.', (string) json_encode($this->api->requests[1]['messages'][2]['content'], \JSON_UNESCAPED_UNICODE));
    }

    public function testOneSectionWrittenAgainAsksForNoField(): void
    {
        $page = $this->givenARecipe('Tarte aux pommes');
        $current = Document::richText('<h1>Tarte</h1>');

        $this->api->answer(FakeClaudeTransport::message([FakeClaudeTransport::text(json_encode(['version' => 1, 'sections' => [['id' => $current->sections[0]->id, 'component' => 'cms:richtext', 'props' => json_encode(['html' => '<h1>Tarte</h1><p>Encore.</p>'], \JSON_THROW_ON_ERROR)]]], \JSON_THROW_ON_ERROR))]));

        $result = $this->generator()->generate(GenerationRequest::section($this->targets()->forPage($page, $this->locale()), $this->locale(), $current, $current->sections[0]->id, new Brief('Plus long.')));

        self::assertNull($result->fields);
        self::assertSame(['version', 'sections'], $this->api->requests[0]['output_config']['format']['schema']['required']);
    }

    private function givenARecipe(string $title): CmsPage
    {
        $page = $this->createPage($title, published: false);
        $page->setPageType('recipe-generated');
        $this->writer()->saveDraft($page, $this->locale(), new PageDraft(title: $title));

        return $page;
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private static function answer(array $fields): array
    {
        return FakeClaudeTransport::message([FakeClaudeTransport::text(json_encode([
            'version' => 1,
            'sections' => [['id' => 'recipe', 'component' => 'cms:richtext', 'props' => json_encode(['html' => '<h1>Tarte aux pommes</h1><p>Une tarte.</p>'], \JSON_THROW_ON_ERROR)]],
            'fields' => json_encode($fields, \JSON_THROW_ON_ERROR),
        ], \JSON_THROW_ON_ERROR))]);
    }

    private function targets(): GenerationTargets
    {
        return $this->getService(GenerationTargets::class);
    }

    private function generator(): PageGenerator
    {
        return new PageGenerator(
            $this->api,
            $this->getService(GeneratorSettings::class),
            $this->getService(PromptBuilder::class),
            $this->getService(GeneratorTools::class),
            $this->getService(DocumentSchema::class),
            $this->getService(DocumentValidator::class),
            $this->getService(GenerationLog::class),
            $this->getService(Pricing::class),
            new NullLogger(),
        );
    }
}
