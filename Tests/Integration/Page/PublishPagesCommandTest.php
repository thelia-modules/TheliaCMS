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

namespace TheliaCMS\Tests\Integration\Page;

use Symfony\Component\Console\Tester\CommandTester;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Page\PublishPagesCommand;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * What the command has to do that writing `published_html` from a script does
 * not: run the publish pipeline, and refuse a page that would show nothing.
 */
final class PublishPagesCommandTest extends CmsIntegrationTestCase
{
    public function testPublishesADraftThroughThePipeline(): void
    {
        $page = $this->createPage('Bulk publish', published: false);
        $pageId = (int) $page->getId();
        $locale = $this->locale();

        $this->contentOf($pageId, $locale)
            ->setDraftHtml('<p><img src="/nowhere.jpg" alt="Une photo"></p>')
            ->setPublishedHtml(null)
            ->setPublishedAt(null)
            ->save();

        $output = $this->publish(['--page' => [(string) $pageId]]);

        self::assertStringContainsString('published', $output);

        $published = (string) $this->contentOf($pageId, $locale)->getPublishedHtml();

        // The pipeline ran: these attributes are added by the ImageRewriter,
        // after the sanitiser, and nothing else in the module writes them.
        self::assertStringContainsString('decoding="async"', $published);
        self::assertStringContainsString('fetchpriority="high"', $published);
        self::assertNotNull($this->contentOf($pageId, $locale)->getPublishedAt());
    }

    public function testRefusesAPageWhoseDraftShowsNothing(): void
    {
        $page = $this->createPage('Nothing to show', published: false);
        $pageId = (int) $page->getId();
        $locale = $this->locale();

        $this->contentOf($pageId, $locale)
            ->setDraftHtml('<style>.a{color:red}</style>&nbsp;')
            ->setPublishedHtml(null)
            ->setPublishedAt(null)
            ->save();

        $output = $this->publish(['--page' => [(string) $pageId]]);

        self::assertStringContainsString('nothing to show', $output);
        self::assertNull($this->contentOf($pageId, $locale)->getPublishedAt());
    }

    public function testDryRunPublishesNothing(): void
    {
        $page = $this->createPage('Left alone', published: false);
        $pageId = (int) $page->getId();
        $locale = $this->locale();

        $this->contentOf($pageId, $locale)->setPublishedHtml(null)->setPublishedAt(null)->save();

        $this->publish(['--page' => [(string) $pageId], '--dry-run' => true]);

        self::assertNull($this->contentOf($pageId, $locale)->getPublishedAt());
    }

    public function testAsksForAPageOrForAllOfThem(): void
    {
        $tester = new CommandTester($this->getService(PublishPagesCommand::class));

        self::assertSame(2, $tester->execute([]));
        self::assertStringContainsString('--all', $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function publish(array $arguments): string
    {
        $command = $this->getService(PublishPagesCommand::class);
        $tester = new CommandTester($command);
        $tester->execute($arguments, ['capture_stderr_separately' => false]);

        return $tester->getDisplay();
    }

    private function contentOf(int $pageId, string $locale): \TheliaCMS\Model\CmsPageContent
    {
        $content = CmsPageContentQuery::create()->filterByPageId($pageId)->filterByLocale($locale)->findOne();

        self::assertNotNull($content, 'The page has no content row to publish.');

        return $content;
    }
}
