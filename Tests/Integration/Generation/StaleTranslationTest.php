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
use Thelia\Model\Content;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Catalogue\Admin\ItemContentWriter;
use TheliaCMS\Catalogue\CatalogueItemType;
use TheliaCMS\Content\Document;
use TheliaCMS\Dashboard\ShowcaseStats;
use TheliaCMS\Generation\ClaudeClientProviderInterface;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContent;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageRevisionQuery;
use TheliaCMS\Page\Admin\CmsPageWriter;
use TheliaCMS\Settings\CmsSettings;
use TheliaCMS\Settings\SiteMode;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;
use TheliaCMS\TheliaCMS;

/**
 * A language written from another one is followed: once the draft it was
 * written from changes, the content screen, the list of pages and the
 * dashboard say it has to be written again.
 */
final class StaleTranslationTest extends AdminScreenTestCase
{
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
    }

    public function testATranslationIsUpToDateUntilTheDraftItWasWrittenFromChanges(): void
    {
        $page = $this->createPage('Nos services', published: false);
        $target = $this->otherLanguage();
        $this->translate($this->pageUrl($page, $target));

        $screen = $this->screen($this->pageUrl($page, $target));
        self::assertSame(1, $screen->filter('[data-testid="cms-content-translation-ok"]')->count(), 'Just written, the translation is up to date.');
        self::assertSame(0, $screen->filter('[data-testid="cms-content-translation-stale"]')->count());
        self::assertSame(0, $this->listOf($target)->filter(\sprintf('[data-testid="cms-page-stale-%d"]', $page->getId()))->count());
        self::assertSame(0, $this->staleCount($target));

        $this->writer()->saveContent($page, $this->locale(), Document::richText('<h1>Nos services</h1><p>Un paragraphe de plus.</p>'));

        $screen = $this->screen($this->pageUrl($page, $target));
        self::assertSame(1, $screen->filter('[data-testid="cms-content-translation-stale"]')->count(), 'The source changed since.');
        self::assertStringContainsString((string) Lang::getDefaultLanguage()->getTitle(), $screen->filter('[data-testid="cms-content-translation-stale"]')->text());
        self::assertSame(1, $screen->filter('[data-testid="cms-content-retranslate"] form')->count(), 'Writing it again is offered, the language being filled.');
        self::assertSame(1, $screen->filter('[data-testid="cms-content-translation-current"] form')->count());
        self::assertSame(1, $this->listOf($target)->filter(\sprintf('[data-testid="cms-page-stale-%d"]', $page->getId()))->count(), 'The list of pages in that language says so.');
        self::assertSame(0, $this->listOf(Lang::getDefaultLanguage())->filter(\sprintf('[data-testid="cms-page-stale-%d"]', $page->getId()))->count(), 'The source itself is not out of date.');
        self::assertSame(1, $this->staleCount($target), 'The dashboard counts it.');
        self::assertStringContainsString('1 page(s) to translate again', $this->dashboard()->filter(\sprintf('[data-testid="cms-dashboard-stale-%s"]', $target->getLocale()))->text());

        // Put back as it was, the source is again what was translated.
        $this->writer()->saveContent($page, $this->locale(), Document::richText('<h1>Nos services</h1><p>Written for the test.</p>'));

        self::assertSame(1, $this->screen($this->pageUrl($page, $target))->filter('[data-testid="cms-content-translation-ok"]')->count());
    }

    public function testAnOutOfDateTranslationIsWrittenAgainAndTheDraftItReplacesIsKept(): void
    {
        $page = $this->createPage('Nos tarifs', published: false);
        $target = $this->otherLanguage();
        $this->translate($this->pageUrl($page, $target), '<h1>Our prices</h1>');
        $this->writer()->saveContent($page, $this->locale(), Document::richText('<h1>Nos tarifs</h1><p>Nouveaux tarifs.</p>'));

        $this->api->answer(FakeClaudeTransport::message([FakeClaudeTransport::text(self::document('<h1>Our prices</h1><p>New prices.</p>'))]));
        $form = $this->screen($this->pageUrl($page, $target))->filter('[data-testid="cms-content-retranslate"] form')->form();
        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        self::assertStringContainsString('Nouveaux tarifs.', json_encode($this->api->requests[1]['messages'], \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR), 'The current source is what is written from.');
        self::assertStringContainsString('New prices.', (string) $this->content($page, $target)?->getDraftHtml());
        self::assertSame(1, CmsPageRevisionQuery::create()->filterByPageId($page->getId())->filterByLocale($target->getLocale())->filterByLabel(CmsPageWriter::DRAFT_LABEL)->count(), 'The translation it replaces can be put back.');
        self::assertSame(1, $this->screen($this->pageUrl($page, $target))->filter('[data-testid="cms-content-translation-ok"]')->count());
    }

    public function testATranslationMarkedUpToDateIsNoLongerReported(): void
    {
        $page = $this->createPage('Nos horaires', published: false);
        $target = $this->otherLanguage();
        $this->translate($this->pageUrl($page, $target), '<h1>Our hours</h1>');
        $this->writer()->saveContent($page, $this->locale(), Document::richText('<h1>Nos horaires</h1><p>Une coquille corrigée.</p>'));

        $form = $this->screen($this->pageUrl($page, $target))->filter('[data-testid="cms-content-translation-current"] form')->form();
        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        self::assertSame(1, $this->screen($this->pageUrl($page, $target))->filter('[data-testid="cms-content-translation-ok"]')->count());
        self::assertStringContainsString('Our hours', (string) $this->content($page, $target)?->getDraftHtml(), 'The translation is left as it is.');
        self::assertSame(1, \count($this->api->requests), 'Nothing was asked of the generator.');
    }

    public function testALanguageWrittenByHandIsFollowedOnceTiedToItsSource(): void
    {
        $page = $this->createPage('Nos engagements', published: false);
        $target = $this->otherLanguage();
        $this->writer()->saveContent($page, $target->getLocale(), Document::richText('<h1>Our commitments</h1>'));

        $screen = $this->screen($this->pageUrl($page, $target));
        self::assertSame(0, $screen->filter('[data-testid="cms-content-translation-ok"], [data-testid="cms-content-translation-stale"]')->count(), 'Written by hand, it is not followed.');

        $form = $screen->filter('[data-testid="cms-content-translation-attach"] form')->form();
        self::assertSame($this->locale(), $form['source']->getValue());
        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));
        self::assertSame(1, $this->screen($this->pageUrl($page, $target))->filter('[data-testid="cms-content-translation-ok"]')->count());

        $this->writer()->saveContent($page, $this->locale(), Document::richText('<h1>Nos engagements</h1><p>Un de plus.</p>'));

        self::assertSame(1, $this->screen($this->pageUrl($page, $target))->filter('[data-testid="cms-content-translation-stale"]')->count());
    }

    public function testAPageInTheBinOrAModelIsNotCountedByTheDashboard(): void
    {
        $target = $this->otherLanguage();
        $binned = $this->createPage('À la corbeille', published: false);
        $model = $this->createPage('Modèle', published: false);
        $this->translate($this->pageUrl($binned, $target));
        $this->translate($this->pageUrl($model, $target));
        $this->writer()->saveContent($binned, $this->locale(), Document::richText('<p>Changé.</p>'));
        $this->writer()->saveContent($model, $this->locale(), Document::richText('<p>Changé.</p>'));

        $binned->setDeletedAt(new \DateTimeImmutable())->save();
        $model->setModel(1)->save();

        self::assertSame(0, $this->staleCount($target));
    }

    public function testATranslationOfACatalogueItemIsFollowedToo(): void
    {
        $factory = new FixtureFactory($this->getPropelConnection());
        $item = $factory->content($factory->folder(), ['title' => 'Notre méthode', 'locale' => $this->locale()]);
        $itemWriter = $this->getService(ItemContentWriter::class);
        $itemWriter->saveContent(CatalogueItemType::Content, (int) $item->getId(), $this->locale(), Document::richText('<h2>Notre méthode</h2>'));
        $target = $this->otherLanguage();
        $this->translate($this->itemUrl($item, $target), '<h2>Our method</h2>');

        self::assertSame(1, $this->screen($this->itemUrl($item, $target))->filter('[data-testid="cms-content-translation-ok"]')->count());

        $itemWriter->saveContent(CatalogueItemType::Content, (int) $item->getId(), $this->locale(), Document::richText('<h2>Notre méthode</h2><p>En trois étapes.</p>'));

        $screen = $this->screen($this->itemUrl($item, $target));
        self::assertSame(1, $screen->filter('[data-testid="cms-content-translation-stale"]')->count());

        $form = $screen->filter('[data-testid="cms-content-translation-current"] form')->form();
        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));
        self::assertSame(1, $this->screen($this->itemUrl($item, $target))->filter('[data-testid="cms-content-translation-ok"]')->count());
    }

    private function translate(string $url, string $html = '<h1>Translated</h1>'): void
    {
        $this->api->answer(FakeClaudeTransport::message([FakeClaudeTransport::text(self::document($html))]));
        $form = $this->screen($url)->filter('[data-testid="cms-content-translate"] form')->form();
        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));
    }

    private function otherLanguage(): Lang
    {
        $other = LangQuery::create()->filterByActive(1)->filterById(Lang::getDefaultLanguage()->getId(), Criteria::NOT_EQUAL)->orderById()->findOne();
        self::assertNotNull($other, 'The shop of the tests has a second active language.');

        return $other;
    }

    private function listOf(Lang $lang): Crawler
    {
        return $this->screen(\sprintf('/admin/cms/pages?edit_language_id=%d', $lang->getId()));
    }

    private function staleCount(Lang $lang): int
    {
        foreach ($this->getService(ShowcaseStats::class)->collect($this->locale())['completeness'] as $row) {
            if ($row['locale'] === $lang->getLocale()) {
                return $row['stale'];
            }
        }

        self::fail('The dashboard has a row for each active language.');
    }

    /**
     * The home of the back office of a showcase site, where the block of the
     * module is shown.
     */
    private function dashboard(): Crawler
    {
        $previous = TheliaCMS::getConfigValue(CmsSettings::SITE_MODE);
        TheliaCMS::setConfigValue(CmsSettings::SITE_MODE, SiteMode::Showcase->value);

        try {
            return $this->screen('/admin');
        } finally {
            TheliaCMS::setConfigValue(CmsSettings::SITE_MODE, $previous ?? SiteMode::Commerce->value);
        }
    }

    private function content(CmsPage $page, Lang $lang): ?CmsPageContent
    {
        return CmsPageContentQuery::create()->filterByPageId($page->getId())->filterByLocale($lang->getLocale())->findOne();
    }

    private function writer(): CmsPageWriter
    {
        return $this->getService(CmsPageWriter::class);
    }

    private function pageUrl(CmsPage $page, Lang $lang): string
    {
        return \sprintf('/admin/cms/pages/%d/content?edit_language_id=%d', $page->getId(), $lang->getId());
    }

    private function itemUrl(Content $item, Lang $lang): string
    {
        return \sprintf('/admin/cms/catalogue/content/%d/content?edit_language_id=%d', $item->getId(), $lang->getId());
    }

    private static function document(string $html): string
    {
        return json_encode(['version' => 1, 'sections' => [['id' => 'content', 'component' => 'cms:richtext', 'props' => ['html' => $html]]]], \JSON_THROW_ON_ERROR);
    }
}
