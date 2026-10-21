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

namespace TheliaCMS\Tests\Integration\Generation\Component;

use Psr\Log\NullLogger;
use TheliaCMS\Content\SectionCatalog;
use TheliaCMS\Content\Site\SiteComponent;
use TheliaCMS\Content\Site\SiteComponentRepository;
use TheliaCMS\Content\Site\SiteComponentValidator;
use TheliaCMS\Front\BlockStyles;
use TheliaCMS\Generation\Component\ComponentGenerator;
use TheliaCMS\Generation\Component\ComponentPrompt;
use TheliaCMS\Generation\Component\ComponentRequest;
use TheliaCMS\Generation\GenerationLog;
use TheliaCMS\Generation\GenerationRefusedException;
use TheliaCMS\Generation\GenerationTarget;
use TheliaCMS\Generation\GeneratorSettings;
use TheliaCMS\Generation\InvalidGenerationException;
use TheliaCMS\Generation\Pricing;
use TheliaCMS\Generation\ShopContext;
use TheliaCMS\Model\CmsPageGenerationQuery;
use TheliaCMS\Skill\SkillRepository;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\Tests\Integration\Generation\FakeClaudeTransport;
use TheliaCMS\Tests\Unit\Content\Site\PriceTable;

/**
 * A request becomes a component the site can keep, through the schema of the
 * answer and as many corrections as it needs; every call is in the ledger.
 */
final class ComponentGeneratorTest extends CmsIntegrationTestCase
{
    private FakeClaudeTransport $api;

    protected function setUp(): void
    {
        parent::setUp();

        $this->api = new FakeClaudeTransport();
    }

    public function testARequestBecomesAComponentTheSiteCanKeep(): void
    {
        $this->api->answer(self::answer(PriceTable::component()));

        $result = $this->generator()->generate(new ComponentRequest('Un tableau de prix de deux à quatre formules.', $this->locale()));

        self::assertSame('Site:PriceTable', $result->component->name());
        self::assertSame('light', $result->component->sample['tone'], 'What is handed back is what the validator gave.');
        self::assertSame(1, $result->turns);

        $request = $this->api->requests[0];
        self::assertSame(ComponentPrompt::schema(), $request['output_config']['format']['schema'], 'The answer is constrained to a component.');
        self::assertArrayNotHasKey('tools', $request, 'Making a component needs no tool of the site.');
        self::assertSame(['type' => 'ephemeral'], end($request['system'])['cache_control'], 'The rules are cached.');
        self::assertStringContainsString('var(--cms-color-accent)', json_encode($request['system'], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES), 'The tokens of the stylesheet are named.');
        self::assertStringContainsString('`cms:richtext`', $request['messages'][0]['content'], 'The model is told what the site already has.');
        self::assertStringContainsString('Un tableau de prix', $request['messages'][0]['content']);

        $row = CmsPageGenerationQuery::create()->findPk($result->logId);
        self::assertNotNull($row);
        self::assertSame([GenerationTarget::TYPE_COMPONENT, 0, ComponentRequest::KIND_NEW, 'done'], [$row->getTargetType(), (int) $row->getTargetId(), $row->getKind(), $row->getStatus()]);
        self::assertStringContainsString('"code": "PriceTable"', (string) $row->getDocument());
        self::assertNull($this->getService(SiteComponentRepository::class)->find('PriceTable'), 'Nothing is kept before the editor says so.');
    }

    public function testAComponentTheSiteRefusesGoesBackWithEveryProblemAndTheCorrectionIsKept(): void
    {
        $this->api
            ->answer(self::answer(PriceTable::component('<section><h2>Nos formules</h2>{{ props.title|raw }}</section>')))
            ->answer(self::answer(PriceTable::component()));

        $result = $this->generator()->generate(new ComponentRequest('Un tableau de prix.', $this->locale()));

        self::assertSame(2, $result->turns);
        $correction = $this->api->requests[1]['messages'][2]['content'];
        self::assertStringContainsString('cannot be kept', $correction);
        self::assertStringContainsString('"raw"', $correction);
        self::assertSame('assistant', $this->api->requests[1]['messages'][1]['role'], 'The answer refused goes back with the problems.');
    }

    public function testANewComponentTakesAFreeCodeAndAChangeKeepsItsOwn(): void
    {
        $repository = $this->getService(SiteComponentRepository::class);
        $repository->save((new SiteComponentValidator())->validate(PriceTable::component()), SiteComponentRepository::STATUS_READY);
        $current = $repository->find('PriceTable');
        self::assertNotNull($current);
        $renamed = PriceTable::component();
        $renamed = new SiteComponent('PriceGrid', $renamed->label, $renamed->description, $renamed->props, $renamed->sample, $renamed->template);

        $this->api->answer(self::answer(PriceTable::component()))->answer(self::answer($renamed));
        $made = $this->generator()->generate(new ComponentRequest('Un tableau de prix.', $this->locale()));

        self::assertSame('PriceGrid', $made->component->code);
        self::assertStringContainsString('"PriceTable" is already', $this->api->requests[1]['messages'][2]['content']);

        $this->api->answer(self::answer($renamed))->answer(self::answer(PriceTable::component(css: '.plan--featured { border: 2px solid var(--cms-color-accent); }')));
        $id = (int) \TheliaCMS\Model\CmsComponentQuery::create()->findOneByCode('PriceTable')?->getId();
        $changed = $this->generator()->generate(new ComponentRequest('Mets la formule mise en avant en valeur.', $this->locale(), $current, $id));

        self::assertSame('PriceTable', $changed->component->code);
        self::assertStringContainsString('stays "PriceTable"', $this->api->requests[3]['messages'][2]['content']);
        self::assertStringContainsString('"code": "PriceTable"', $this->api->requests[2]['messages'][0]['content'], 'The component to change is sent with the request.');
        self::assertSame([$id, ComponentRequest::KIND_CHANGE], [(int) CmsPageGenerationQuery::create()->findPk($changed->logId)?->getTargetId(), CmsPageGenerationQuery::create()->findPk($changed->logId)?->getKind()]);
    }

    public function testAComponentRefusedThreeTimesIsAnErrorInTheLedger(): void
    {
        $broken = self::answer(PriceTable::component('{% include "base.html.twig" %}'));
        $this->api->answer($broken)->answer($broken)->answer($broken);

        try {
            $this->generator()->generate(new ComponentRequest('Un tableau de prix.', $this->locale()));
            self::fail('A component that never holds is an error.');
        } catch (InvalidGenerationException $exception) {
            self::assertCount(3, $this->api->requests);
            self::assertSame('invalid', CmsPageGenerationQuery::create()->findPk($exception->logId)?->getStatus());
        }
    }

    public function testARefusalOfTheModelIsRecorded(): void
    {
        $this->api->answer(FakeClaudeTransport::message([], 'refusal', [], ['type' => 'refusal', 'category' => 'general_harms', 'explanation' => 'Not this.']));

        try {
            $this->generator()->generate(new ComponentRequest('Something the model declines.', $this->locale()));
            self::fail('A refusal is no component.');
        } catch (GenerationRefusedException $exception) {
            self::assertSame('refused', CmsPageGenerationQuery::create()->findPk($exception->logId)?->getStatus());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function answer(SiteComponent $component): array
    {
        return FakeClaudeTransport::message([FakeClaudeTransport::text(ComponentPrompt::json($component))]);
    }

    private function generator(): ComponentGenerator
    {
        return new ComponentGenerator(
            $this->api,
            $this->getService(GeneratorSettings::class),
            new ComponentPrompt($this->getService(SkillRepository::class), $this->getService(SectionCatalog::class), $this->getService(ShopContext::class), new BlockStyles()),
            new SiteComponentValidator(),
            $this->getService(SiteComponentRepository::class),
            $this->getService(GenerationLog::class),
            $this->getService(Pricing::class),
            new NullLogger(),
        );
    }
}
