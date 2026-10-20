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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DomCrawler\Crawler;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Generation\ClaudeClientProviderInterface;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageGenerationQuery;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * The content screen of a page, against a recorded API: the brief becomes
 * the draft, the outcome is read back from the ledger, a change keeps the
 * sections it does not concern, and publishing puts the draft online.
 */
final class ContentScreenTest extends AdminScreenTestCase
{
    private FakeClaudeTransport $api;

    /** @var ClaudeClientProviderInterface&object{delegate: ClaudeClientProviderInterface} */
    private ClaudeClientProviderInterface $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->api = new FakeClaudeTransport();

        // A service is replaced once per kernel: the provider the container
        // gets delegates, so a test can still change what it answers.
        $this->provider = new class($this->api) implements ClaudeClientProviderInterface {
            public function __construct(public ClaudeClientProviderInterface $delegate)
            {
            }

            public function client(): \Anthropic\Client
            {
                return $this->delegate->client();
            }
        };
        static::getContainer()->set(ClaudeClientProviderInterface::class, $this->provider);
    }

    public function testTheScreenOffersABriefToAPageWithoutContent(): void
    {
        $page = $this->createPage('Nos services', published: false);
        CmsPageContentQuery::create()->filterByPageId($page->getId())->delete();

        $screen = $this->screen($this->url($page->getId()));

        self::assertSame(1, $screen->filter('[data-testid="cms-page-content"]')->count());
        self::assertSame(1, $screen->filter('[data-testid="cms-content-empty"]')->count(), 'A page without content shows the empty state instead of a preview.');
        self::assertSame(0, $screen->filter('[data-testid="cms-content-frame"]')->count());
        self::assertSame(0, $screen->filter('[data-testid="cms-content-sections"]')->count());
        self::assertSame(1, $screen->filter('[data-testid="cms-content-brief"] form input[name="_token"]')->count(), 'The brief form carries the token.');
        self::assertSame(0, $screen->filter('[data-testid="cms-content-notice"]')->count(), 'With a client at hand, nothing warns about the key.');
        self::assertNotNull($screen->filter('[data-testid="cms-content-publish"] button')->attr('disabled'), 'Nothing to publish yet.');
    }

    public function testABriefWritesTheDraftAndTheScreenShowsTheOutcome(): void
    {
        $page = $this->createPage('Nos services', published: false);
        $url = $this->url($page->getId());

        $this->api->answer(FakeClaudeTransport::message([FakeClaudeTransport::text(self::document([
            ['id' => 'hero', 'component' => 'Cms:Hero', 'props' => ['title' => 'Nos services', 'heading_level' => '1', 'text' => 'Ce que nous faisons.']],
            ['id' => 'faq', 'component' => 'Cms:Questions', 'props' => ['title' => 'Questions', 'items' => [['question' => 'Combien ?', 'answer' => '<p>Sur devis.</p>']]]],
        ]))], 'end_turn', ['input_tokens' => 1200, 'output_tokens' => 400]));

        $form = $this->screen($url)->filter('[data-testid="cms-content-brief"] form')->form();
        $form['cms_brief[text]'] = 'Une page qui présente nos services de conseil.';
        $form['cms_brief[tone]'] = 'direct';

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#/admin/cms/pages/\d+/content\?.*done=\d+#', $location, 'The screen is reloaded with the row of the ledger.');

        $content = CmsPageContentQuery::create()->filterByPageId($page->getId())->filterByLocale($this->locale())->findOne();
        self::assertStringContainsString('"component":"Cms:Hero"', (string) $content?->getDraftDocument(), 'The draft is the document the model wrote.');
        self::assertStringContainsString('Nos services', (string) $content?->getDraftHtml());
        self::assertNull($content?->getPublishedHtml(), 'Nothing went online.');
        self::assertStringContainsString('Tone asked for: direct', $this->api->requests[0]['messages'][0]['content']);

        $screen = $this->screen($location);

        self::assertSame(1, $screen->filter('[data-testid="cms-builder-flash-success"]')->count(), 'The outcome is read from the ledger and shown.');
        self::assertStringContainsString('The draft is written', $screen->filter('[data-testid="cms-builder-flash-success"]')->text());
        self::assertSame(1, $screen->filter('[data-testid="cms-content-frame"]')->count(), 'The draft is previewed in the theme.');
        self::assertSame(['hero', 'faq'], $screen->filter('[data-testid="cms-content-sections"] li[data-section]')->each(static fn (Crawler $item): string => (string) $item->attr('data-section')));
        self::assertStringContainsString('Nos services', $screen->filter('[data-testid="cms-content-sections"] li[data-section]')->first()->text(), 'A section is quoted by its title.');
        self::assertSame(1, $screen->filter('[data-testid="cms-content-history"] tbody tr')->count(), 'The history lists the call.');
        self::assertSame(2, $screen->filter('form[data-cms-generate] input[name$="[section]"]')->count(), 'Every section has its rewrite form.');
        self::assertSame('Une page qui présente nos services de conseil.', $screen->filter('[data-testid="cms-content-brief"] textarea')->text(), 'The brief is back in its form, to be corrected rather than typed again.');
        self::assertSame('direct', $screen->filter('[data-testid="cms-content-brief"] input[name="cms_brief[tone]"]')->attr('value'));
        self::assertSame('{"text":"Une page qui présente nos services de conseil.","tone":"direct","audience":null}', CmsPageGenerationQuery::create()->filterByTargetId($page->getId())->findOne()?->getBrief());
    }

    public function testALanguageWithoutContentIsWrittenFromAnotherOne(): void
    {
        $page = $this->createPage('Nos services', published: false);
        $source = Lang::getDefaultLanguage();
        $target = LangQuery::create()->filterByActive(1)->filterById($source->getId(), Criteria::NOT_EQUAL)->orderById()->findOne();
        self::assertNotNull($target, 'The shop of the tests has a second active language.');
        $url = \sprintf('/admin/cms/pages/%d/content?edit_language_id=%d', $page->getId(), $target->getId());
        $this->api->answer(FakeClaudeTransport::message([FakeClaudeTransport::text(self::document([['id' => 'content', 'component' => 'cms:richtext', 'props' => ['html' => '<h1>Translated</h1><p>Written for the test.</p>']]]))]));

        $screen = $this->screen($url);

        self::assertSame(0, $screen->filter('[data-testid="cms-content-empty"]')->count(), 'A page written in another language is not announced as blank.');
        self::assertSame(1, $screen->filter('[data-testid="cms-content-translate"]')->count());
        self::assertSame($source->getLocale(), $screen->filter('[data-testid="cms-content-translate"] option[selected]')->attr('value'), 'The first language of the shop that holds a draft is picked.');
        self::assertStringContainsString((string) $source->getTitle(), $screen->filter('[data-testid="cms-content-translate-btn"]')->text());

        $form = $screen->filter('[data-testid="cms-content-translate"] form')->form();
        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        $prompt = $this->api->requests[0]['messages'][0]['content'];
        self::assertStringContainsString(\sprintf('The document the page holds in %s (`%s`)', $source->getTitle(), $source->getLocale()), $prompt);
        self::assertStringContainsString('<h1>Nos services</h1><p>Written for the test.</p>', $prompt, 'The draft of the other language is what the model writes from.');
        self::assertStringContainsString(\sprintf('Write this document in %s (`%s`)', $target->getTitle(), $target->getLocale()), $prompt);

        $content = CmsPageContentQuery::create()->filterByPageId($page->getId())->filterByLocale($target->getLocale())->findOne();
        self::assertStringContainsString('Translated', (string) $content?->getDraftHtml(), 'The new draft is stored on the row of its language.');
        self::assertStringContainsString('Nos services', (string) CmsPageContentQuery::create()->filterByPageId($page->getId())->filterByLocale($source->getLocale())->findOne()?->getDraftHtml(), 'The source draft is untouched.');
        self::assertSame('translation', CmsPageGenerationQuery::create()->filterByTargetId($page->getId())->filterByLocale($target->getLocale())->findOne()?->getKind());
        self::assertSame(0, $this->screen($url)->filter('[data-testid="cms-content-translate"]')->count(), 'Once written, the offer is gone.');
    }

    public function testTheOutcomeOfAnotherPageIsNotShown(): void
    {
        $page = $this->createPage('Mine', published: false);
        $other = $this->createPage('Other', published: false);
        $this->api->answer(FakeClaudeTransport::message([FakeClaudeTransport::text(self::document([['id' => 'content', 'component' => 'cms:richtext', 'props' => ['html' => '<h1>Other</h1>']]]))]));

        $form = $this->screen($this->url($other->getId()))->filter('[data-testid="cms-content-brief"] form')->form();
        $form['cms_brief[text]'] = 'Something.';
        $this->send('POST', $form->getUri(), $form->getPhpValues());
        $row = CmsPageGenerationQuery::create()->filterByTargetId($other->getId())->findOne();

        $screen = $this->screen($this->url($page->getId()).'&done='.$row?->getId());

        self::assertSame(0, $screen->filter('.cms-toast')->count());
    }

    public function testAScriptReadsTheProgressAsAStreamOfEvents(): void
    {
        $page = $this->createPage('Streamed', published: false);
        $this->api
            ->answer(FakeClaudeTransport::message([FakeClaudeTransport::toolUse('toolu_1', 'search_pages', ['query' => ''])], 'tool_use'))
            ->answer(FakeClaudeTransport::message([FakeClaudeTransport::text(self::document([['id' => 'content', 'component' => 'cms:richtext', 'props' => ['html' => '<h1>Streamed</h1><p>Done.</p>']]]))]));

        $form = $this->screen($this->url($page->getId()))->filter('[data-testid="cms-content-brief"] form')->form();
        $form['cms_brief[text]'] = 'A streamed page.';

        $this->client->request('POST', $form->getUri(), $form->getPhpValues(), [], [...self::XHR, 'HTTP_ACCEPT' => 'text/event-stream']);
        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));
        self::assertSame('no', $response->headers->get('X-Accel-Buffering'));

        // The content of a stream is what the callback wrote, which the
        // browser of the test captured; the response itself holds none.
        $events = self::events((string) $this->client->getInternalResponse()->getContent());

        self::assertSame(['start', 'turn', 'turn', 'done'], array_values(array_filter(array_column($events, 'event'), static fn (string $name): bool => 'delta' !== $name)), 'The deltas of the text aside, every step is announced.');
        self::assertSame(['search_pages'], $events[1]['data']['tools'], 'The tools of a turn are named.');
        self::assertMatchesRegularExpression('#/content\?.*done=\d+$#', $events[3]['data']['redirect'], 'The last event says where to go.');
        self::assertStringContainsString('<p>Done.</p>', (string) CmsPageContentQuery::create()->filterByPageId($page->getId())->findOne()?->getDraftHtml(), 'The draft was saved before the last event.');
    }

    public function testAFailureIsAnEventAndARowOfTheLedger(): void
    {
        $page = $this->createPage('Refused', published: false);
        $this->api->answer(FakeClaudeTransport::message([], 'refusal', [], ['type' => 'refusal', 'category' => 'general_harms', 'explanation' => 'Not this.']));

        $form = $this->screen($this->url($page->getId()))->filter('[data-testid="cms-content-brief"] form')->form();
        $form['cms_brief[text]'] = 'Something the model declines.';

        $this->client->request('POST', $form->getUri(), $form->getPhpValues(), [], [...self::XHR, 'HTTP_ACCEPT' => 'text/event-stream']);
        $events = self::events((string) $this->client->getInternalResponse()->getContent());

        self::assertSame(['start', 'error'], array_column($events, 'event'), 'A refusal is no turn: the model wrote nothing.');
        self::assertStringContainsString('declined', $events[1]['data']['message']);

        $screen = $this->screen($events[1]['data']['redirect']);
        self::assertSame(1, $screen->filter('[data-testid="cms-builder-flash-warning"]')->count(), 'A refusal is a warning, with the figures of the call.');
        self::assertStringContainsString('<h1>Refused</h1>', (string) CmsPageContentQuery::create()->filterByPageId($page->getId())->findOne()?->getDraftHtml(), 'The draft was left as it was.');
    }

    public function testAChangeKeepsTheSectionsItDoesNotConcern(): void
    {
        $page = $this->createPage('Revised', published: false);
        $this->api->answer(FakeClaudeTransport::message([FakeClaudeTransport::text(self::document([['id' => 'content', 'component' => 'cms:richtext', 'props' => ['html' => '<h1>Revised</h1><p>After.</p>']]]))]));

        $screen = $this->screen($this->url($page->getId()));
        $form = $screen->filter('[data-testid="cms-content-change"] form')->form();
        $form['cms_change[text]'] = 'Say After instead of Written for the test.';

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));
        self::assertStringContainsString('<h1>Revised</h1><p>Written for the test.</p>', $this->api->requests[0]['messages'][0]['content'], 'The document the page holds is sent with the change.');
        self::assertStringContainsString('<p>After.</p>', (string) CmsPageContentQuery::create()->filterByPageId($page->getId())->findOne()?->getDraftHtml());
        self::assertSame('revision', CmsPageGenerationQuery::create()->filterByTargetId($page->getId())->findOne()?->getKind());
    }

    public function testOneSectionIsWrittenAgainByItsOwnForm(): void
    {
        $page = $this->createPage('Sectioned', published: false);
        $this->api->answer(FakeClaudeTransport::message([FakeClaudeTransport::text(self::document([['id' => 'content', 'component' => 'cms:richtext', 'props' => ['html' => '<h1>Sectioned</h1><p>Shorter.</p>']]]))]));

        $screen = $this->screen($this->url($page->getId()));
        $form = $screen->filter('[data-testid="cms-content-sections"] li[data-section="content"] form[data-cms-generate]')->form();
        $form['cms_change_content[text]'] = 'Shorter.';

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));
        self::assertStringContainsString('Write the section `content` again', $this->api->requests[0]['messages'][0]['content']);
        self::assertSame('section', CmsPageGenerationQuery::create()->filterByTargetId($page->getId())->findOne()?->getKind());
    }

    public function testAnEmptyBriefIsRefusedBeforeAnyCall(): void
    {
        $page = $this->createPage('Empty brief', published: false);

        $form = $this->screen($this->url($page->getId()))->filter('[data-testid="cms-content-brief"] form')->form();
        $form['cms_brief[text]'] = '   ';

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));
        self::assertSame([], $this->api->requests, 'Nothing was sent to the API.');
        self::assertSame(1, $this->screen($this->url($page->getId()))->filter('[data-testid="cms-builder-flash-danger"]')->count());
    }

    public function testPublishingPutsTheDraftOnline(): void
    {
        $page = $this->createPage('Published from the screen', published: false);
        $screen = $this->screen($this->url($page->getId()));
        $form = $screen->filter('[data-testid="cms-content-publish"]')->form();

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));
        self::assertStringContainsString('<h1>Published from the screen</h1>', (string) CmsPageContentQuery::create()->filterByPageId($page->getId())->findOne()?->getPublishedHtml());
        self::assertSame(1, $this->screen($this->url($page->getId()))->filter('[data-testid="cms-builder-flash-success"]')->count());
    }

    public function testTheGeneratorIsARightOfItsOwn(): void
    {
        $page = $this->createPage('No right', published: false);
        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([
            CmsResources::PAGE => [AccessManager::VIEW, AccessManager::UPDATE],
        ]));

        $screen = $this->screen($this->url($page->getId()));
        self::assertSame(1, $screen->filter('[data-testid="cms-content-no-right"]')->count());
        self::assertNotNull($screen->filter('[data-testid="cms-content-generate"]')->attr('disabled'), 'The button is off without the right.');
        self::assertSame(0, $screen->filter('[data-testid="cms-content-change"]')->count());

        $token = $this->tokenOfTheScripts($screen);
        self::assertSame(403, $this->send('POST', $this->generateUrl($page->getId()), ['_token' => $token, 'cms_brief' => ['text' => 'Try anyway.']]));
        self::assertSame([], $this->api->requests);

        self::assertSame(302, $this->send('POST', $this->publishUrl($page->getId()), ['_token' => $token]), 'Publishing takes the right to change pages, which this profile has.');
    }

    public function testAPageInTheBinHasNoContentScreen(): void
    {
        $page = $this->createPage('Binned', published: false);
        $page->setDeletedAt(new \DateTimeImmutable())->save();

        self::assertSame(404, $this->send('GET', $this->url($page->getId())));
        self::assertSame(404, $this->send('GET', $this->url(999999999)));
    }

    public function testWithoutAKeyTheScreenSaysSoAndKeepsTheButtonOff(): void
    {
        $page = $this->createPage('No key', published: false);
        $this->provider->delegate = new class implements ClaudeClientProviderInterface {
            public function client(): \Anthropic\Client
            {
                throw \TheliaCMS\Generation\GenerationUnavailableException::noKey();
            }
        };

        $screen = $this->screen($this->url($page->getId()));

        self::assertStringContainsString('ANTHROPIC_API_KEY', $screen->filter('[data-testid="cms-content-notice"]')->text());
        self::assertNotNull($screen->filter('[data-testid="cms-content-generate"]')->attr('disabled'));
    }

    private function url(int $pageId): string
    {
        return \sprintf('/admin/cms/pages/%d/content?edit_language_id=%d', $pageId, Lang::getDefaultLanguage()->getId());
    }

    private function generateUrl(int $pageId): string
    {
        return \sprintf('/admin/cms/pages/%d/content/generate', $pageId);
    }

    private function publishUrl(int $pageId): string
    {
        return \sprintf('/admin/cms/pages/%d/content/publish', $pageId);
    }

    /**
     * @param list<array{id: string, component: string, props: array<string, mixed>}> $sections
     */
    private static function document(array $sections): string
    {
        return json_encode(['version' => 1, 'sections' => $sections], \JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<array{event: string, data: array<string, mixed>}>
     */
    private static function events(string $stream): array
    {
        $events = [];

        foreach (array_filter(array_map('trim', explode("\n\n", $stream))) as $chunk) {
            $event = 'message';
            $data = '';

            foreach (explode("\n", $chunk) as $line) {
                if (str_starts_with($line, 'event:')) {
                    $event = trim(substr($line, 6));
                } elseif (str_starts_with($line, 'data:')) {
                    $data .= trim(substr($line, 5));
                }
            }

            $events[] = ['event' => $event, 'data' => json_decode($data, true, 512, \JSON_THROW_ON_ERROR)];
        }

        return $events;
    }
}
