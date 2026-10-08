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
use TheliaCMS\Page\PageLayout;
use TheliaCMS\Page\PublishedPage;

/**
 * `layout` stays readable for the hooks written against 1.x, derived from the
 * type of the page.
 */
final class PublishedPageTest extends TestCase
{
    public function testAFormerLayoutReadsAsThatLayout(): void
    {
        self::assertSame(PageLayout::FullWidth, $this->page('full-width')->layout);
        self::assertSame(PageLayout::Landing, $this->page('landing')->layout);
    }

    public function testAnyOtherTypeReadsAsTheDefaultLayout(): void
    {
        self::assertSame(PageLayout::Default, $this->page('recipe')->layout);
    }

    public function testResolvingTheBlocksKeepsTheType(): void
    {
        $page = $this->page('recipe')->withHtml('<p>resolved</p>');

        self::assertSame('recipe', $page->pageType);
        self::assertSame('<p>resolved</p>', $page->html);
    }

    private function page(string $pageType): PublishedPage
    {
        return new PublishedPage(id: 1, locale: 'fr_FR', title: 'Gratin', pageType: $pageType, html: '', css: '');
    }
}
