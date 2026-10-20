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
use TheliaCMS\Content\DocumentSchema;
use TheliaCMS\Content\DocumentValidator;
use TheliaCMS\Generation\Brief;
use TheliaCMS\Generation\GenerationException;
use TheliaCMS\Generation\GenerationLog;
use TheliaCMS\Generation\GenerationObserverInterface;
use TheliaCMS\Generation\GenerationRefusedException;
use TheliaCMS\Generation\GenerationRequest;
use TheliaCMS\Generation\GenerationTarget;
use TheliaCMS\Generation\GeneratorSettings;
use TheliaCMS\Generation\InvalidGenerationException;
use TheliaCMS\Generation\PageGenerator;
use TheliaCMS\Generation\Pricing;
use TheliaCMS\Generation\PromptBuilder;
use TheliaCMS\Generation\Tool\GeneratorTools;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageGenerationQuery;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * The generator against a recorded API: what it sends, what it does with
 * what comes back, and what it writes to the ledger whatever the outcome.
 */
final class PageGeneratorTest extends CmsIntegrationTestCase
{
    private FakeClaudeTransport $api;

    protected function setUp(): void
    {
        parent::setUp();

        $this->api = new FakeClaudeTransport();
    }

    public function testABriefBecomesADocumentThroughTheToolsAndTheSchema(): void
    {
        $linked = $this->createPage('Nos tarifs');
        $page = $this->createPage('Nos services', published: false);
        $locale = $this->locale();

        $this->api
            ->answer(FakeClaudeTransport::message([
                FakeClaudeTransport::text('Let me look at the pages of the site.'),
                FakeClaudeTransport::toolUse('toolu_1', 'search_pages', ['query' => 'tarif']),
            ], 'tool_use', ['input_tokens' => 1200, 'cache_creation_input_tokens' => 1000]))
            ->answer(FakeClaudeTransport::message([
                FakeClaudeTransport::text(json_encode([
                    'version' => 1,
                    'sections' => [
                        ['id' => 'hero', 'component' => 'cms:richtext', 'props' => ['html' => '<h1>Nos services</h1><p>Ce que nous faisons. <a href="'.$linked->getUrl($locale).'">Voir les tarifs</a></p>']],
                        ['id' => 'contact', 'component' => 'Partial:cms-form', 'props' => ['form' => '4']],
                    ],
                ], \JSON_THROW_ON_ERROR)),
            ], 'end_turn', ['input_tokens' => 1300, 'cache_read_input_tokens' => 1000, 'output_tokens' => 400]));

        $result = $this->generator()->generate(GenerationRequest::fromBrief(GenerationTarget::page($page, $locale), $locale, new Brief('Une page qui présente nos services de conseil, avec un lien vers les tarifs.', tone: 'direct')));

        self::assertSame(['hero', 'contact'], array_map(static fn ($section) => $section->id, $result->document->sections));
        self::assertSame(4, $result->document->sections[1]->props['form'], 'The document comes back normalised by the validator.');
        self::assertSame(2, $result->turns);
        self::assertSame(2500, $result->usage->inputTokens);
        self::assertSame(1000, $result->usage->cacheReadTokens);
        self::assertSame(1000, $result->usage->cacheWriteTokens);
        self::assertGreaterThan(0, $result->costUsd);

        // What was sent.
        self::assertCount(2, $this->api->requests);
        $first = $this->api->requests[0];

        self::assertSame('claude-opus-5-5', $first['model']);
        self::assertSame('high', $first['output_config']['effort']);
        self::assertSame('json_schema', $first['output_config']['format']['type']);
        self::assertSame(['version', 'sections'], $first['output_config']['format']['schema']['required']);
        self::assertSame(['type' => 'ephemeral'], $first['system'][2]['cache_control'], 'The catalogue, last of the stable blocks, is what the cache holds.');
        self::assertArrayNotHasKey('cache_control', $first['system'][0]);
        self::assertStringContainsString('# Skills', $first['system'][1]['text']);
        self::assertStringContainsString('## `cms:richtext`', $first['system'][2]['text']);
        self::assertSame('auto', $first['tool_choice']['type']);
        self::assertContains('search_pages', array_column($first['tools'], 'name'));
        self::assertArrayNotHasKey('strict', $first['tools'][0], 'A strict tool would join the grammar of the output schema, which is at its limit.');
        self::assertStringContainsString('Une page qui présente nos services', $first['messages'][0]['content']);
        self::assertStringContainsString('Tone asked for: direct', $first['messages'][0]['content']);
        self::assertStringContainsString('Title: Nos services', $first['messages'][0]['content']);

        $second = $this->api->requests[1];

        self::assertSame('assistant', $second['messages'][1]['role']);
        self::assertSame('tool_result', $second['messages'][2]['content'][0]['type']);
        self::assertSame('toolu_1', $second['messages'][2]['content'][0]['tool_use_id']);
        self::assertStringContainsString('"title":"Nos tarifs"', $second['messages'][2]['content'][0]['content'], 'The tool answered with the pages of the site.');

        // The ledger.
        $row = CmsPageGenerationQuery::create()->findPk($result->logId);

        self::assertNotNull($row);
        self::assertSame(GenerationLog::STATUS_DONE, $row->getStatus());
        self::assertSame('brief', $row->getKind());
        self::assertSame(2500, $row->getInputTokens());
        self::assertSame(2, $row->getTurns());
        self::assertStringContainsString('"component":"cms:richtext"', (string) $row->getDocument());
        self::assertStringContainsString('nos services', (string) $row->getPrompt());
    }

    public function testADocumentTheSiteRefusesGoesBackOnceWithItsProblems(): void
    {
        $page = $this->createPage('Page corrigée', published: false);
        $valid = json_encode(['version' => 1, 'sections' => [['id' => 'hero', 'component' => 'cms:richtext', 'props' => ['html' => '<h1>Bonjour</h1>']]]], \JSON_THROW_ON_ERROR);

        $this->api
            ->answer(FakeClaudeTransport::message([FakeClaudeTransport::text('{"version":1,"sections":[{"id":"hero","component":"Cms:Nope","props":{}}]}')]))
            ->answer(FakeClaudeTransport::message([FakeClaudeTransport::text("```json\n".$valid."\n```")]));

        $result = $this->generator()->generate(GenerationRequest::fromBrief($this->target($page), $this->locale(), new Brief('Bonjour')));

        self::assertSame('<h1>Bonjour</h1>', $result->document->sections[0]->props['html']);
        self::assertStringContainsString('"Cms:Nope", which is not a component', $this->api->requests[1]['messages'][2]['content'], 'The problems are sent back for correction.');
        self::assertSame(2, $result->turns);
    }

    public function testADocumentRefusedAfterTwoCorrectionsIsAnError(): void
    {
        $page = $this->createPage('Page cassée', published: false);
        $broken = FakeClaudeTransport::message([FakeClaudeTransport::text('{"version":1,"sections":[{"id":"x","component":"Cms:Nope","props":{}}]}')]);
        $this->api->answer($broken)->answer($broken)->answer($broken);

        try {
            $this->generator()->generate(GenerationRequest::fromBrief($this->target($page), $this->locale(), new Brief('Bonjour')));
            self::fail('The generation should have failed.');
        } catch (InvalidGenerationException $exception) {
            self::assertCount(1, $exception->problems);
        }

        self::assertCount(3, $this->api->requests, 'One answer, two corrections, then the error.');
        self::assertSame(GenerationLog::STATUS_INVALID, $this->lastStatus($page));
    }

    public function testARefusalOfTheModelIsSaidAndLogged(): void
    {
        $page = $this->createPage('Page refusée', published: false);
        $this->api->answer(FakeClaudeTransport::message([], 'refusal', [], ['type' => 'refusal', 'category' => 'general_harms', 'explanation' => 'Not this.']));

        try {
            $this->generator()->generate(GenerationRequest::fromBrief($this->target($page), $this->locale(), new Brief('Something')));
            self::fail('The generation should have been refused.');
        } catch (GenerationRefusedException $exception) {
            self::assertSame('general_harms', $exception->category);
            self::assertStringContainsString('declined', $exception->getMessage());
        }

        self::assertSame(GenerationLog::STATUS_REFUSED, $this->lastStatus($page));
        self::assertSame('general_harms', CmsPageGenerationQuery::create()->filterByTargetId($page->getId())->findOne()?->getStopCategory());
    }

    public function testAnAnswerCutShortIsAFailureNotAHalfPage(): void
    {
        $page = $this->createPage('Page coupée', published: false);
        $this->api->answer(FakeClaudeTransport::message([FakeClaudeTransport::text('{"version":1,"sections":[')], 'max_tokens'));

        $this->expectException(GenerationException::class);
        $this->expectExceptionMessage('cut at');

        try {
            $this->generator()->generate(GenerationRequest::fromBrief($this->target($page), $this->locale(), new Brief('Long')));
        } finally {
            self::assertSame(GenerationLog::STATUS_FAILED, $this->lastStatus($page));
        }
    }

    public function testAnErrorOfTheApiIsAFailureAndTheDraftIsLeftAsItIs(): void
    {
        $page = $this->createPage('Page en panne', html: '<h1>Avant</h1>', published: false);
        // Nothing queued: the transport answers the way the API does when it
        // is down, a 500 with an error body.

        try {
            $this->generator()->generate(GenerationRequest::fromBrief($this->target($page), $this->locale(), new Brief('Something')));
            self::fail('The generation should have failed.');
        } catch (GenerationException $exception) {
            self::assertStringContainsString('The Claude API answered', $exception->getMessage());
            self::assertNotNull($exception->logId);
        }

        self::assertSame(GenerationLog::STATUS_FAILED, $this->lastStatus($page));
        self::assertStringContainsString('<h1>Avant</h1>', (string) CmsPageContentQuery::create()->filterByPageId($page->getId())->findOne()?->getDraftHtml(), 'The draft is untouched.');
    }

    public function testARevisionSendsTheCurrentDocumentAndAsksForTheWholeOneBack(): void
    {
        $page = $this->createPage('Page revue', html: '<h1>Avant</h1>', published: false);
        $content = CmsPageContentQuery::create()->filterByPageId($page->getId())->filterByLocale($this->locale())->findOne();
        $current = \TheliaCMS\Content\Document::fromJson($content?->getDraftDocument());

        self::assertNotNull($current);

        $this->api->answer(FakeClaudeTransport::message([FakeClaudeTransport::text(json_encode(['version' => 1, 'sections' => [['id' => 'content', 'component' => 'cms:richtext', 'props' => ['html' => '<h1>Après</h1>']]]], \JSON_THROW_ON_ERROR))]));

        $result = $this->generator()->generate(GenerationRequest::revision($this->target($page), $this->locale(), $current, new Brief('Change the heading to Après.')));

        self::assertSame('<h1>Après</h1>', $result->document->sections[0]->props['html']);
        self::assertStringContainsString('# The document the page holds today', $this->api->requests[0]['messages'][0]['content']);
        self::assertStringContainsString('<h1>Avant</h1>', $this->api->requests[0]['messages'][0]['content']);
        self::assertSame('revision', CmsPageGenerationQuery::create()->filterByTargetId($page->getId())->findOne()?->getKind());
    }

    public function testTheObserverIsToldOfEveryTurnAndOfTheCorrectionAndTheLedgerNamesThePage(): void
    {
        $page = $this->createPage('Réassurance', published: false);
        $locale = $this->locale();

        $unknown = json_encode(['version' => 1, 'sections' => [['id' => 'inner', 'component' => 'Cms:Nothing', 'props' => []]]], \JSON_THROW_ON_ERROR);
        $valid = json_encode(['version' => 1, 'sections' => [['id' => 'figures', 'component' => 'cms:richtext', 'props' => ['html' => '<h2>En chiffres</h2><p>48 h pour livrer.</p>']]]], \JSON_THROW_ON_ERROR);

        $this->api
            ->answer(FakeClaudeTransport::message([FakeClaudeTransport::text($unknown)]))
            ->answer(FakeClaudeTransport::message([FakeClaudeTransport::text($valid)]));

        $observer = new class implements GenerationObserverInterface {
            /** @var list<string> */
            public array $events = [];

            public int $characters = 0;

            public function onTurn(int $turn, array $tools): void
            {
                $this->events[] = \sprintf('turn %d: %s', $turn, implode(',', $tools) ?: 'answer');
            }

            public function onText(int $characters): void
            {
                $this->characters = $characters;
            }

            public function onCorrection(int $round, array $problems): void
            {
                $this->events[] = \sprintf('correction %d', $round);
            }
        };

        $result = $this->generator()->generate(
            GenerationRequest::fromBrief($this->target($page), $locale, new Brief('Trois chiffres qui rassurent.')),
            $observer,
        );

        self::assertSame('cms:richtext', $result->document->sections[0]->component);
        self::assertSame(['turn 1: answer', 'correction 1', 'turn 2: answer'], $observer->events, 'The observer is told of every turn and of the correction.');
        self::assertSame(mb_strlen($valid), $observer->characters, 'The observer is told of the text as it is streamed.');
        self::assertTrue($this->api->requests[0]['stream'] ?? false, 'The answer is read as a stream.');

        self::assertStringContainsString('Cms:Nothing', $this->api->requests[1]['messages'][2]['content'], 'The unknown component is sent back as a problem.');

        $row = CmsPageGenerationQuery::create()->filterByTargetType(GenerationTarget::TYPE_PAGE)->filterByTargetId($page->getId())->findOne();
        self::assertNotNull($row, 'The ledger names the page as the target.');
        self::assertSame($result->logId, (int) $row->getId());
        self::assertSame($row->getId(), $this->getService(GenerationLog::class)->find((int) $row->getId(), $this->target($page), $locale)?->getId());
        self::assertNull($this->getService(GenerationLog::class)->find((int) $row->getId(), GenerationTarget::page($this->createPage('Other'), $locale), $locale), 'A row is found for its own target only.');
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

    private function target(\TheliaCMS\Model\CmsPage $page): GenerationTarget
    {
        return GenerationTarget::page($page, $this->locale());
    }

    private function lastStatus(\TheliaCMS\Model\CmsPage $page): ?string
    {
        return CmsPageGenerationQuery::create()->filterByTargetId($page->getId())->orderById(\Propel\Runtime\ActiveQuery\Criteria::DESC)->findOne()?->getStatus();
    }
}
