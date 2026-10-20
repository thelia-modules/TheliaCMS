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

namespace TheliaCMS\Tests\Unit\Page;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Page\PublishedPage;

/**
 * The object the theme hooks receive: resolving its dynamic blocks changes
 * its HTML and nothing else.
 */
final class PublishedPageTest extends TestCase
{
    public function testResolvingTheBlocksKeepsTheType(): void
    {
        $page = $this->page('recipe')->withHtml('<p>resolved</p>');

        self::assertSame('recipe', $page->pageType);
        self::assertSame('<p>resolved</p>', $page->html);
    }

    public function testResolvingTheBlocksKeepsTheTag(): void
    {
        $page = new PublishedPage(id: 1, locale: 'fr_FR', title: 'Gratin', pageType: 'recipe', html: '', tag: 'Recette');

        self::assertSame('Recette', $page->withHtml('<p>resolved</p>')->tag);
        self::assertNull($this->page('recipe')->tag, 'A page without a tag has none.');
    }

    private function page(string $pageType): PublishedPage
    {
        return new PublishedPage(id: 1, locale: 'fr_FR', title: 'Gratin', pageType: $pageType, html: '');
    }
}
