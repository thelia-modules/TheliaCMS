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

namespace TheliaCMS\Tests\Integration\Dashboard;

use TheliaCMS\Dashboard\ShowcaseStats;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * The figures on the dashboard of a showcase site. They are counts over the
 * real tables, and the only thing worth checking is that they count the right
 * rows: a dashboard that is quietly wrong is worse than no dashboard.
 */
final class ShowcaseStatsTest extends CmsIntegrationTestCase
{
    public function testPublishingAPageMovesItFromTheDraftsToThePublished(): void
    {
        $before = $this->collect();

        $page = $this->createPage('Page à publier', published: false);
        $withDraft = $this->collect();

        self::assertSame($before['pages_draft'] + 1, $withDraft['pages_draft']);
        self::assertSame($before['pages_published'], $withDraft['pages_published']);

        $this->writer()->publish($page, $this->locale());
        $after = $this->collect();

        self::assertSame($before['pages_published'] + 1, $after['pages_published']);
        self::assertSame($before['pages_draft'], $after['pages_draft']);
    }

    public function testAPageInTheBinIsCountedNowhere(): void
    {
        $before = $this->collect();
        $page = $this->createPage('Page à jeter');

        $this->writer()->moveToTrash($page);
        $after = $this->collect();

        self::assertSame($before['pages_published'], $after['pages_published']);
        self::assertSame($before['pages_draft'], $after['pages_draft']);
    }

    public function testTheLastEditedPageComesFirst(): void
    {
        $this->createPage('Page modifiée en dernier');

        $recent = $this->collect()['recent_pages'];

        self::assertNotSame([], $recent);
        self::assertSame('Page modifiée en dernier', $recent[0]['title']);
    }

    /**
     * @return array<string, mixed>
     */
    private function collect(): array
    {
        return $this->getService(ShowcaseStats::class)->collect($this->locale());
    }
}
