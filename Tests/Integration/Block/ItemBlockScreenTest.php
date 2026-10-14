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

namespace TheliaCMS\Tests\Integration\Block;

use Symfony\Component\DomCrawler\Crawler;
use Thelia\Model\Category;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Block\Item\ItemBlockRepository;
use TheliaCMS\Block\Item\ItemType;
use TheliaCMS\Block\Item\ItemTypes;
use TheliaCMS\ImportExport\SiteExporter;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * The block card of a catalogue screen and its two routes, over HTTP: the
 * token is required, the block is created on the first demand and opens in
 * the builder, the reusable screens and sources leave it alone, and the card
 * deletes it.
 */
final class ItemBlockScreenTest extends AdminScreenTestCase
{
    public function testTheCardCreatesOpensAndDeletesTheBlockOfACategory(): void
    {
        $category = (new FixtureFactory($this->getPropelConnection()))->category();
        $categoryId = (int) $category->getId();
        $screenUrl = \sprintf('/admin/categories/update?category_id=%d', $categoryId);
        $edit = \sprintf('/admin/cms/item-blocks/category/%d/edit', $categoryId);
        $delete = \sprintf('/admin/cms/item-blocks/category/%d/delete', $categoryId);

        self::assertSame(403, $this->send('POST', $edit), 'A post without the token is refused.');
        self::assertNull($this->itemBlocks()->linkFor(ItemType::Category, $categoryId));

        $screen = $this->screen($screenUrl);
        $card = $screen->filter('[data-testid="cms-item-block-card"]');
        self::assertSame(1, $card->count(), 'The category screen carries the card.');
        $token = $this->tokenOfTheCard($card);

        self::assertSame(302, $this->send('POST', $edit, ['_token' => $token]));
        $block = $this->itemBlocks()->blockFor(ItemType::Category, $categoryId);
        self::assertNotNull($block);
        $blockId = (int) $block->getId();
        self::assertStringContainsString(\sprintf('/admin/cms/blocks/%d/builder', $blockId), (string) $this->client->getResponse()->headers->get('Location'));

        $this->screen(\sprintf('/admin/cms/blocks/%d/builder', $blockId));

        self::assertSame(302, $this->send('POST', $edit, ['_token' => $token]), 'The second demand opens the same block.');
        self::assertSame($blockId, (int) $this->itemBlocks()->blockFor(ItemType::Category, $categoryId)?->getId());

        self::assertSame(404, $this->send('GET', \sprintf('/admin/cms/blocks/%d', $blockId)), 'The settings screen of reusable blocks refuses it.');
        self::assertSame(404, $this->send('POST', \sprintf('/admin/cms/blocks/%d/delete', $blockId), ['_token' => $token]));

        $this->client->request('GET', '/admin/cms/partials/sources/blocks', [], [], self::XHR);
        self::assertNotContains($blockId, array_column(json_decode((string) $this->client->getResponse()->getContent(), true), 'id'), 'The editor is not offered it as a reusable block.');

        self::assertNotContains($block->getCode(), array_column($this->getService(SiteExporter::class)->export()['blocks'], 'code'), 'The export leaves it out.');

        self::assertSame(403, $this->send('POST', $delete));
        self::assertSame(302, $this->send('POST', $delete, ['_token' => $token]));
        self::assertStringContainsString($screenUrl, (string) $this->client->getResponse()->headers->get('Location'));
        self::assertNull($this->itemBlocks()->linkFor(ItemType::Category, $categoryId));
    }

    public function testAnUnknownTypeOrItemAnswers404(): void
    {
        $token = $this->tokenOfTheScripts($this->screen('/admin/cms/blocks'));

        self::assertSame(404, $this->send('POST', '/admin/cms/item-blocks/page/1/edit', ['_token' => $token]));
        self::assertSame(404, $this->send('POST', '/admin/cms/item-blocks/category/999999999/edit', ['_token' => $token]));
    }

    public function testAStoreIsNotAnItemWithoutTheDealerModule(): void
    {
        if ($this->getService(ItemTypes::class)->isAvailable(ItemType::Dealer)) {
            self::markTestSkipped('The Dealer module is active on this shop.');
        }

        $token = $this->tokenOfTheScripts($this->screen('/admin/cms/blocks'));

        self::assertSame(404, $this->send('POST', '/admin/cms/item-blocks/dealer/1/edit', ['_token' => $token]));
    }

    private function tokenOfTheCard(Crawler $card): string
    {
        $button = $card->filter('[data-cms-item-block-token]');

        self::assertGreaterThan(0, $button->count(), 'The card carries no token.');

        return (string) $button->first()->attr('data-cms-item-block-token');
    }

    private function itemBlocks(): ItemBlockRepository
    {
        return $this->getService(ItemBlockRepository::class);
    }
}
