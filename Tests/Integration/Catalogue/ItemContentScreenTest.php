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

namespace TheliaCMS\Tests\Integration\Catalogue;

use Thelia\Model\Content;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Catalogue\Admin\ItemContentTabHook;
use TheliaCMS\Catalogue\Admin\ItemContentWriter;
use TheliaCMS\Catalogue\CatalogueItemType;
use TheliaCMS\Content\Document;
use TheliaCMS\Generation\ClaudeClientProviderInterface;
use TheliaCMS\Model\CmsItemContentQuery;
use TheliaCMS\Model\CmsPageGenerationQuery;
use TheliaCMS\Preview\ItemPreviewLink;
use TheliaCMS\Tests\Integration\Generation\FakeClaudeTransport;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * The document of a catalogue item in the back office: the tab on the edit
 * screen of the item, the content screen of a page used for it, and the
 * signed preview of its draft.
 */
final class ItemContentScreenTest extends AdminScreenTestCase
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

    public function testTheEditScreenOfTheItemLeadsToItsContentScreen(): void
    {
        $content = $this->content();

        $screen = $this->screen(\sprintf('/admin/content/update/%d', $content->getId()));
        $tab = $screen->filter(\sprintf('#%s [data-testid="cms-item-content-tab"]', ItemContentTabHook::TAB_ID));

        self::assertSame(1, $screen->filter('[data-testid="content-tab-'.ItemContentTabHook::TAB_ID.'"]')->count(), 'The edit screen of a content has the tab of the CMS.');
        self::assertSame(1, $tab->count());
        self::assertGreaterThan(0, $tab->filter('[data-testid="cms-item-content-language"]')->count(), 'A row per language.');
        self::assertStringContainsString(
            \sprintf('/admin/cms/catalogue/content/%d/content', $content->getId()),
            (string) $tab->filter('[data-testid="cms-item-content-edit"]')->first()->attr('href'),
        );
    }

    public function testTheContentScreenOpensForTheItemAndAnUnknownItemIsNotFound(): void
    {
        $content = $this->content();

        $screen = $this->screen($this->url($content));

        self::assertSame(1, $screen->filter('[data-testid="cms-item-content"]')->count());
        self::assertSame(1, $screen->filter('[data-testid="cms-content-empty"]')->count(), 'Nothing written yet.');
        self::assertStringContainsString(\sprintf('/admin/content/update/%d', $content->getId()), (string) $screen->filter('[data-testid="cms-content-back"]')->attr('href'));

        $this->client->request('GET', '/admin/cms/catalogue/content/999999/content');
        self::assertSame(404, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', \sprintf('/admin/cms/catalogue/brand/%d/content', $content->getId()));
        self::assertSame(404, $this->client->getResponse()->getStatusCode(), 'A brand carries no document.');
    }

    public function testASectionAddedByHandIsPublishedOnTheItem(): void
    {
        $content = $this->content();

        $form = $this->screen($this->url($content))->filter('[data-testid="cms-content-add-cms:richtext"]')->form();
        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        $row = CmsItemContentQuery::create()->filterByItemType('content')->filterByItemId($content->getId())->filterByLocale($this->locale())->findOne();
        self::assertNotNull(Document::fromJson($row?->getDraftDocument()), 'The section is in the draft of the item.');
        self::assertNull($row?->getPublishedHtml());

        $publish = $this->screen($this->url($content))->filter('form[data-testid="cms-content-publish"]')->form();
        self::assertSame(302, $this->send('POST', $publish->getUri(), $publish->getPhpValues()));

        $row->reload();
        self::assertNotNull($row->getPublishedAt(), 'Published from the screen.');
        self::assertNotEmpty($row->getPublishedHtml());

        $tab = $this->screen(\sprintf('/admin/content/update/%d', $content->getId()));
        $unpublish = $tab->filter('[data-testid="cms-item-content-unpublish"]')->first()->form();
        self::assertSame(302, $this->send('POST', $unpublish->getUri(), $unpublish->getPhpValues()));

        $row->reload();
        self::assertNull($row->getPublishedAt(), 'Taken offline from the tab.');
    }

    public function testABriefWritesTheDraftOfTheItemAndIsFiledUnderIt(): void
    {
        $content = $this->content();

        $this->api->answer(FakeClaudeTransport::message([FakeClaudeTransport::text(json_encode(['version' => 1, 'sections' => [
            ['id' => 'faq', 'component' => 'cms:richtext', 'props' => ['html' => '<h2>Questions</h2><p>Sur devis.</p>']],
        ]], \JSON_THROW_ON_ERROR))], 'end_turn', ['input_tokens' => 1200, 'output_tokens' => 400]));

        $form = $this->screen($this->url($content))->filter('[data-testid="cms-content-brief"] form')->form();
        $form['cms_brief[text]'] = 'Les questions fréquentes sur ce contenu.';

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        $row = CmsItemContentQuery::create()->filterByItemType('content')->filterByItemId($content->getId())->filterByLocale($this->locale())->findOne();
        self::assertStringContainsString('Sur devis.', (string) $row?->getDraftHtml());
        self::assertSame(1, CmsPageGenerationQuery::create()->filterByTargetType('content')->filterByTargetId($content->getId())->count(), 'The call is in the ledger, under the item.');
    }

    public function testTheSignedLinkShowsTheDraftAndATamperedOneIsNotFound(): void
    {
        $content = $this->content();
        $this->getService(ItemContentWriter::class)->saveContent(CatalogueItemType::Content, (int) $content->getId(), $this->locale(), Document::richText('<h2>Brouillon</h2><p>Pas encore en ligne.</p>'));

        $url = $this->getService(ItemPreviewLink::class)->urlFor(CatalogueItemType::Content, (int) $content->getId(), $this->locale());

        $preview = $this->screen($url);
        self::assertStringContainsString('Pas encore en ligne.', $preview->filter('.cms-page-content')->text());
        self::assertSame('noindex, nofollow', $this->client->getResponse()->headers->get('X-Robots-Tag'));

        $this->client->request('GET', substr($url, 0, -1).('0' === substr($url, -1) ? '1' : '0'));
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    private function content(): Content
    {
        $factory = new FixtureFactory($this->getPropelConnection());

        return $factory->content($factory->folder(), ['title' => 'Notre méthode']);
    }

    private function url(Content $content): string
    {
        return \sprintf('/admin/cms/catalogue/content/%d/content', $content->getId());
    }
}
