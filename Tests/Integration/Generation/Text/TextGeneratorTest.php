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

namespace TheliaCMS\Tests\Integration\Generation\Text;

use Psr\Log\NullLogger;
use TheliaCMS\Generation\Brief;
use TheliaCMS\Generation\GenerationLog;
use TheliaCMS\Generation\GenerationRefusedException;
use TheliaCMS\Generation\GenerationRequest;
use TheliaCMS\Generation\GenerationTarget;
use TheliaCMS\Generation\GeneratorSettings;
use TheliaCMS\Generation\InvalidGenerationException;
use TheliaCMS\Generation\Pricing;
use TheliaCMS\Generation\ShopContext;
use TheliaCMS\Generation\Text\TextField;
use TheliaCMS\Generation\Text\TextGenerator;
use TheliaCMS\Generation\Text\TextPrompt;
use TheliaCMS\Generation\Text\TextRequest;
use TheliaCMS\Generation\Text\TextSanitizer;
use TheliaCMS\Generation\Tool\GeneratorTools;
use TheliaCMS\Model\CmsPageGenerationQuery;
use TheliaCMS\Skill\SkillRepository;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\Tests\Integration\Generation\FakeClaudeTransport;

/**
 * The text of one rich-text field, written from a brief: formatted text only,
 * with the tools of the catalogue and the pages, and the settings of the texts
 * rather than the ones of the pages. Nothing is saved but the row of the
 * ledger.
 */
final class TextGeneratorTest extends CmsIntegrationTestCase
{
    private FakeClaudeTransport $api;

    protected function setUp(): void
    {
        parent::setUp();

        $this->api = new FakeClaudeTransport();
    }

    public function testABriefBecomesFormattedTextAndTheCallIsLogged(): void
    {
        $this->api->answer(self::answer('<p>Un <strong>bol</strong> en grès, tourné à la main.</p><ul><li>Passe au four</li></ul>'));

        $result = $this->generator()->generate($this->request('Présente le bol en deux phrases.', TextField::Summary, '<p>Ancien résumé.</p>'));

        self::assertSame('<p>Un <strong>bol</strong> en grès, tourné à la main.</p><ul><li>Passe au four</li></ul>', $result->html);

        $sent = $this->api->requests[0];
        $settings = $this->getService(GeneratorSettings::class);
        self::assertSame($settings->textModel(), $sent['model'], 'The model of the texts.');
        self::assertSame($settings->textMaxTokens(), $sent['max_tokens']);
        self::assertSame($settings->textEffort(), $sent['output_config']['effort']);
        self::assertSame(TextPrompt::schema(), $sent['output_config']['format']['schema'], 'The answer is constrained to the envelope of a text.');
        self::assertSame(TextGenerator::TOOLS, array_column($sent['tools'], 'name'), 'The catalogue, the pages and the skills: no picture, no section.');

        $user = $sent['messages'][0]['content'];
        self::assertStringContainsString('Bol en grès', $user, 'The object is named.');
        self::assertStringContainsString('The summary', $user, 'The field is named.');
        self::assertStringContainsString('<p>Ancien résumé.</p>', $user, 'What the field holds is sent, to rewrite or complete.');
        self::assertStringContainsString('Présente le bol', $user);
        self::assertStringContainsString('# Skills', json_encode($sent['system'], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));

        $row = CmsPageGenerationQuery::create()->findPk($result->logId);
        self::assertNotNull($row);
        self::assertSame(['product', 42, GenerationRequest::KIND_TEXT, GenerationLog::STATUS_DONE], [$row->getTargetType(), (int) $row->getTargetId(), $row->getKind(), $row->getStatus()]);
        self::assertSame($result->html, $row->getDocument());
    }

    public function testWhatIsNotFormattedTextIsTakenOutAndItsTextKept(): void
    {
        $this->api->answer(self::answer('<div class="x"><h1>Le bol</h1><p style="color:red" onclick="alert(1)">Grès <img src="https://example.com/a.png"> clair.</p><script>alert(1)</script><table><tr><td>Taille</td></tr></table><a href="javascript:alert(1)">lien</a></div>'));

        $html = $this->generator()->generate($this->request('Décris le bol.'))->html;

        self::assertStringNotContainsString('<div', $html);
        self::assertStringNotContainsString('<h1', $html);
        self::assertStringContainsString('Le bol', $html, 'The text of a heading outside the set is kept.');
        self::assertStringNotContainsString('style', $html);
        self::assertStringNotContainsString('onclick', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<table', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('Grès', $html);
    }

    public function testACallOfAToolIsAnsweredAndTheTextFollows(): void
    {
        $this->api
            ->answer(FakeClaudeTransport::message([FakeClaudeTransport::toolUse('toolu_1', 'search_products', ['query' => 'bol'])], 'tool_use'))
            ->answer(self::answer('<p>Voir aussi nos bols.</p>'));

        $result = $this->generator()->generate($this->request('Décris le bol.'));

        self::assertSame(2, $result->turns);
        self::assertSame('tool_result', $this->api->requests[1]['messages'][2]['content'][0]['type'], 'The call is answered by the site.');
    }

    public function testAnAnswerThatCannotBeUsedGoesBackOnceThenFails(): void
    {
        $this->api
            ->answer(FakeClaudeTransport::message([FakeClaudeTransport::text('Voici le texte.')]))
            ->answer(self::answer('<p>  </p>'));

        try {
            $this->generator()->generate($this->request('Décris le bol.'));
            self::fail('A text that cannot be used was handed back.');
        } catch (InvalidGenerationException $exception) {
            self::assertStringContainsString('cannot be used', $this->api->requests[1]['messages'][2]['content'], 'The first answer went back with its problem.');
            self::assertSame(GenerationLog::STATUS_INVALID, CmsPageGenerationQuery::create()->findPk($exception->logId)?->getStatus());
        }
    }

    public function testARefusalIsLoggedAndThrown(): void
    {
        $this->api->answer(FakeClaudeTransport::message([], 'refusal', stopDetails: ['type' => 'refusal', 'category' => 'cyber', 'explanation' => null]));

        try {
            $this->generator()->generate($this->request('Décris le bol.'));
            self::fail('A refusal was handed back as a text.');
        } catch (GenerationRefusedException $exception) {
            self::assertSame(GenerationLog::STATUS_REFUSED, CmsPageGenerationQuery::create()->findPk($exception->logId)?->getStatus());
        }
    }

    private function request(string $brief, TextField $field = TextField::Description, string $current = ''): TextRequest
    {
        $target = GenerationTarget::field('product', 42, 'Bol en grès', $this->locale());

        return new TextRequest(GenerationRequest::text($target, $this->locale(), new Brief($brief)), $field, $current);
    }

    private static function answer(string $html): array
    {
        return FakeClaudeTransport::message([FakeClaudeTransport::text(json_encode(['html' => $html], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE))]);
    }

    private function generator(): TextGenerator
    {
        return new TextGenerator(
            $this->api,
            $this->getService(GeneratorSettings::class),
            new TextPrompt($this->getService(SkillRepository::class), $this->getService(ShopContext::class)),
            $this->getService(GeneratorTools::class),
            new TextSanitizer(),
            $this->getService(GenerationLog::class),
            $this->getService(Pricing::class),
            new NullLogger(),
        );
    }
}
