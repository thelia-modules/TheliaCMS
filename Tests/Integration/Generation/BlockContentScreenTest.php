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

use Thelia\Model\Lang;
use TheliaCMS\Generation\ClaudeClientProviderInterface;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Model\CmsBlockContentQuery;
use TheliaCMS\Model\CmsPageGenerationQuery;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * The content screen of a reusable block: the same screen as a page, the
 * generator told it writes a block, the draft previewed in the theme through
 * a link signed for the block, and publishing through the block writer.
 */
final class BlockContentScreenTest extends AdminScreenTestCase
{
    private FakeClaudeTransport $api;

    protected function setUp(): void
    {
        parent::setUp();

        $this->api = new FakeClaudeTransport();
        static::getContainer()->set(ClaudeClientProviderInterface::class, $this->api);
    }

    public function testABriefWritesTheBlockAndPublishingPutsItOnline(): void
    {
        $block = (new CmsBlock())->setCode('screen-block');
        $block->setLocale($this->locale())->setTitle('Réassurance');
        $block->save();
        $url = \sprintf('/admin/cms/blocks/%d/content?edit_language_id=%d', $block->getId(), Lang::getDefaultLanguage()->getId());

        $screen = $this->screen($url);
        self::assertSame(1, $screen->filter('[data-testid="cms-block-content"]')->count());
        self::assertStringContainsString(\sprintf('/admin/cms/blocks/%d', $block->getId()), (string) $screen->filter('[data-testid="cms-content-back"]')->attr('href'), 'The bar leads back to the settings of the block.');
        self::assertSame(0, $screen->filter('[data-testid="cms-page-preview-btn"]')->count(), 'A block has no address of its own.');

        $this->api->answer(FakeClaudeTransport::message([FakeClaudeTransport::text(json_encode(['version' => 1, 'sections' => [
            ['id' => 'figures', 'component' => 'Cms:Figures', 'props' => ['title' => 'En chiffres', 'items' => [['value' => '48 h', 'label' => 'Livraison']]]],
        ]], \JSON_THROW_ON_ERROR))]));

        $form = $screen->filter('[data-testid="cms-content-brief"] form')->form();
        $form['cms_brief[text]'] = 'Trois chiffres qui rassurent.';

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));
        self::assertStringContainsString('# The block', $this->api->requests[0]['messages'][0]['content']);
        self::assertSame('block', CmsPageGenerationQuery::create()->filterByTargetId($block->getId())->findOne()?->getTargetType());

        $content = CmsBlockContentQuery::create()->filterByBlockId($block->getId())->filterByLocale($this->locale())->findOne();
        self::assertStringContainsString('48 h', (string) $content?->getDraftHtml());
        self::assertNull($content?->getPublishedHtml());

        $screen = $this->screen((string) $this->client->getResponse()->headers->get('Location'));
        $preview = (string) $screen->filter('[data-testid="cms-content-frame"]')->attr('src');
        self::assertStringContainsString('/cms-preview/block/'.$block->getId().'/', $preview, 'The draft is previewed through a link signed for the block.');

        $this->client->request('GET', $preview);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('48 h', (string) $this->client->getResponse()->getContent());
        self::assertSame('noindex, nofollow', $this->client->getResponse()->headers->get('X-Robots-Tag'));

        $publish = $screen->filter('[data-testid="cms-content-publish"]')->form();
        self::assertSame(302, $this->send('POST', $publish->getUri(), $publish->getPhpValues()));
        self::assertStringContainsString('48 h', (string) CmsBlockContentQuery::create()->filterByBlockId($block->getId())->filterByLocale($this->locale())->findOne()?->getPublishedHtml());
    }

    public function testALinkSignedForAPageOpensNoBlock(): void
    {
        $block = (new CmsBlock())->setCode('signed-block');
        $block->setLocale($this->locale())->setTitle('Signed');
        $block->save();

        $links = $this->getService(\TheliaCMS\Preview\PreviewLink::class);
        $forThePage = parse_url($links->urlFor((int) $block->getId(), $this->locale()), \PHP_URL_PATH);

        self::assertSame(404, $this->send('GET', str_replace('/cms-preview/', '/cms-preview/block/', (string) $forThePage)));
    }
}
