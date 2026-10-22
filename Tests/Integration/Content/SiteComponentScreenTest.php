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

use Thelia\Core\Security\AccessManager;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Content\Site\SiteComponent;
use TheliaCMS\Content\Site\SiteComponentRepository;
use TheliaCMS\Generation\ClaudeClientProviderInterface;
use TheliaCMS\Generation\Component\ComponentPrompt;
use TheliaCMS\Model\CmsComponentQuery;
use TheliaCMS\Model\CmsPageGenerationQuery;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\Tests\Integration\Generation\FakeClaudeTransport;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;
use TheliaCMS\Tests\Unit\Content\Site\PriceTable;

/**
 * A component made in the back office: asked for, looked at in the theme,
 * changed, kept, then a component like the others on the list.
 */
final class SiteComponentScreenTest extends AdminScreenTestCase
{
    private const string NEW = '/admin/cms/components/site/new';

    private const string GENERATE = '/admin/cms/components/site/generate';

    private FakeClaudeTransport $api;

    protected function setUp(): void
    {
        parent::setUp();

        $this->api = new FakeClaudeTransport();
        $api = $this->api;
        static::getContainer()->set(ClaudeClientProviderInterface::class, new class($api) implements ClaudeClientProviderInterface {
            public function __construct(private FakeClaudeTransport $api)
            {
            }

            public function client(): \Anthropic\Client
            {
                return $this->api->client();
            }
        });
    }

    public function testARequestEndsOnACandidateDrawnInTheThemeAndNothingIsKeptYet(): void
    {
        $this->api->answer(self::answer(PriceTable::component(css: '.plan--featured { border: 2px solid var(--cms-color-accent); }')));

        $location = $this->ask('Un tableau de prix de deux à quatre formules.');
        self::assertMatchesRegularExpression('#/admin/cms/components/site/candidates/\d+$#', $location);

        $screen = $this->screen($location);
        self::assertSame('done', $screen->filter('[data-testid="cms-site-component-outcome"]')->attr('data-status'));
        self::assertSame(1, $screen->filter('[data-testid="cms-site-component-keep"]')->count());
        self::assertSame(3, $screen->filter('[data-testid="cms-site-component-props"] tbody tr')->count(), 'Every prop is listed: title, plans, tone.');
        self::assertNull(CmsComponentQuery::create()->findOneByCode('PriceTable'), 'Nothing is kept before the editor says so.');

        $preview = (string) $screen->filter('[data-testid="cms-site-component-frame"]')->attr('src');
        self::assertMatchesRegularExpression('#^/cms-component-preview/candidate-\d+/[a-z]{2}_[A-Z]{2}/\d+/[a-f0-9]{64}$#', $preview);

        $this->client->request('GET', $preview);
        $page = (string) $this->client->getResponse()->getContent();
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('class="cms-site cms-site--price-table"', $page, 'The sample is drawn in the theme.');
        self::assertStringContainsString('.cms-site--price-table .plan--featured', $page, 'Its stylesheet is printed scoped.');
        self::assertSame('noindex, nofollow', $this->client->getResponse()->headers->get('X-Robots-Tag'));

        $this->client->request('GET', substr($preview, 0, -1).('0' === substr($preview, -1) ? '1' : '0'));
        self::assertSame(404, $this->client->getResponse()->getStatusCode(), 'A preview answers its signature only.');
    }

    public function testAChangeOfACandidateThenKeepingItMakesAComponentOfTheSite(): void
    {
        $this->api
            ->answer(self::answer(PriceTable::component()))
            ->answer(self::answer(PriceTable::component(css: '.plan { padding: 1rem; }')));

        $first = $this->ask('Un tableau de prix.');
        $second = $this->ask('Ajoute de l’espace dans chaque formule.', ['candidate' => self::idOf($first)], $first);

        self::assertStringContainsString('"code": "PriceTable"', $this->api->requests[1]['messages'][0]['content'], 'The change starts from the candidate.');

        $keep = $this->screen($second)->filter('[data-testid="cms-site-component-keep"]')->form();
        self::assertSame(302, $this->send('POST', $keep->getUri(), $keep->getPhpValues()));
        self::assertStringEndsWith('/admin/cms/components/site/PriceTable', (string) $this->client->getResponse()->headers->get('Location'));

        $kept = $this->getService(SiteComponentRepository::class)->find('PriceTable');
        self::assertSame('.plan { padding: 1rem; }', $kept?->css);
        self::assertSame(SiteComponentRepository::STATUS_READY, $this->getService(SiteComponentRepository::class)->status('PriceTable'));
        $id = (int) CmsComponentQuery::create()->findOneByCode('PriceTable')?->getId();
        self::assertSame($id, (int) CmsPageGenerationQuery::create()->findPk(self::idOf($second))?->getTargetId(), 'The call it came from starts its history.');

        $screen = $this->screen('/admin/cms/components/site/PriceTable');
        self::assertSame(1, $screen->filter('[data-testid="cms-site-component-history"] li a')->count());
        self::assertMatchesRegularExpression('#^/cms-component-preview/component-PriceTable/#', (string) $screen->filter('[data-testid="cms-site-component-frame"]')->attr('src'));

        $list = $this->screen('/admin/cms/components');
        self::assertStringContainsString('Made on this site', $list->filter('[data-testid="cms-component-origin-Site:PriceTable"]')->text());
        self::assertSame(1, $list->filter('[data-testid="cms-component-open-Site:PriceTable"]')->count());

        $this->client->request('GET', '/cms/site-components.css');
        self::assertStringContainsString('.cms-site--price-table .plan {padding: 1rem;}', (string) $this->client->getResponse()->getContent(), 'The site serves the stylesheet of what it kept, scoped.');
    }

    public function testAChangeOfAKeptComponentReplacesItOnceKept(): void
    {
        $repository = $this->getService(SiteComponentRepository::class);
        $repository->save(PriceTable::component(), SiteComponentRepository::STATUS_READY);
        $id = (int) CmsComponentQuery::create()->findOneByCode('PriceTable')?->getId();
        $this->api->answer(self::answer(PriceTable::component(css: '.plan { color: var(--cms-color-text); }')));

        $candidate = $this->ask('Change la couleur du texte.', ['component' => 'PriceTable'], '/admin/cms/components/site/PriceTable');

        self::assertSame($id, (int) CmsPageGenerationQuery::create()->findPk(self::idOf($candidate))?->getTargetId());
        self::assertSame('', $repository->find('PriceTable')?->css, 'The kept version stays until the new one is kept.');

        $keep = $this->screen($candidate)->filter('[data-testid="cms-site-component-keep"]')->form();
        self::assertSame(302, $this->send('POST', $keep->getUri(), $keep->getPhpValues()));
        self::assertSame('.plan { color: var(--cms-color-text); }', $repository->find('PriceTable')?->css);
        self::assertSame(1, CmsComponentQuery::create()->count(), 'The component is replaced, not doubled.');
    }

    public function testACallThatFailsIsShownWithWhatWentWrong(): void
    {
        $this->api->answer(FakeClaudeTransport::message([], 'refusal', [], ['type' => 'refusal', 'category' => 'general_harms', 'explanation' => 'Not this.']));

        $screen = $this->screen($this->ask('Something the model declines.'));

        self::assertSame('refused', $screen->filter('[data-testid="cms-site-component-outcome"]')->attr('data-status'));
        self::assertSame(0, $screen->filter('[data-testid="cms-site-component-keep"]')->count());
        self::assertSame(1, $screen->filter('[data-testid="cms-site-component-request"]')->count(), 'The editor may ask again.');
    }

    public function testSeeingTheComponentsIsNotTheRightToMakeOne(): void
    {
        $token = $this->tokenOfTheForm($this->screen(self::NEW), self::GENERATE);

        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([
            CmsResources::SETTINGS => [AccessManager::VIEW, AccessManager::UPDATE],
        ]));

        $screen = $this->screen(self::NEW);
        self::assertSame(1, $screen->filter('[data-testid="cms-site-component-not-allowed"]')->count());
        self::assertSame(0, $this->screen('/admin/cms/components')->filter('[data-testid="cms-components-new"]')->count());
        self::assertSame(403, $this->send('POST', self::GENERATE, ['_token' => $token, 'request' => 'Un tableau de prix.']));
        self::assertSame([], $this->api->requests, 'The API is not called.');
    }

    /**
     * Sends a request from the form of a screen and gives where it ended.
     *
     * @param array<string, int|string> $hidden
     */
    private function ask(string $text, array $hidden = [], string $from = self::NEW): string
    {
        $token = $this->tokenOfTheForm($this->screen($from), self::GENERATE);

        self::assertSame(302, $this->send('POST', self::GENERATE, ['_token' => $token, 'request' => $text] + $hidden));

        return (string) $this->client->getResponse()->headers->get('Location');
    }

    private static function idOf(string $candidateUrl): int
    {
        return (int) substr($candidateUrl, (int) strrpos($candidateUrl, '/') + 1);
    }

    /**
     * @return array<string, mixed>
     */
    private static function answer(SiteComponent $component): array
    {
        return FakeClaudeTransport::message([FakeClaudeTransport::text(ComponentPrompt::json($component))]);
    }
}
