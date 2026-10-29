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
use TheliaCMS\Content\Document;
use TheliaCMS\Model\CmsPageContent;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Page\RenderPagesCommand;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * A draft saved before a change of the module or of the theme is brought up
 * to date from its document, and nothing goes online.
 */
final class RenderPagesCommandTest extends CmsIntegrationTestCase
{
    public function testAStaleDraftIsRenderedAgainAndNothingIsPublished(): void
    {
        $page = $this->createPage('Rendered again', published: true);
        $pageId = (int) $page->getId();
        $published = $this->contentOf($pageId)->getPublishedHtml();

        $this->contentOf($pageId)->setDraftHtml('<p>rendered by an older module</p>')->save();

        $output = $this->render(['--page' => [(string) $pageId]]);

        self::assertStringContainsString('1 draft(s) rendered again, 0 unchanged.', $output);
        self::assertStringContainsString(\sprintf('class="%s"', $this->richTextClass()), (string) $this->contentOf($pageId)->getDraftHtml(), 'The draft is the rendering of its document today.');
        self::assertSame($published, $this->contentOf($pageId)->getPublishedHtml(), 'What is online is left as it is.');

        self::assertStringContainsString('0 draft(s) rendered again, 1 unchanged.', $this->render(['--page' => [(string) $pageId]]), 'A draft already up to date is not written again.');
    }

    public function testADraftWhoseCustomCodeWouldBeLostIsKept(): void
    {
        $page = $this->createPage('Embedded video', published: false);
        $pageId = (int) $page->getId();
        $stored = '<div class="cms-page-content"><div data-cms-section="content" class="cms-richtext"><iframe src="https://www.youtube-nocookie.com/embed/abc"></iframe></div></div>';

        $this->contentOf($pageId)
            ->setDraftDocument(Document::richText('<iframe src="https://www.youtube-nocookie.com/embed/abc"></iframe>')->toJson())
            ->setDraftHtml($stored)
            ->save();

        $output = $this->render(['--page' => [(string) $pageId]]);

        self::assertStringContainsString('custom code would be taken out', $output);
        self::assertSame($stored, $this->contentOf($pageId)->getDraftHtml(), 'The command holds no right to custom code: the iframe an author with the right saved stays.');
    }

    public function testAsksForAPageOrForAllOfThem(): void
    {
        self::assertStringContainsString('--page', $this->render([]));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function render(array $arguments): string
    {
        $tester = new CommandTester($this->getService(RenderPagesCommand::class));
        $tester->execute($arguments, ['capture_stderr_separately' => false]);

        return $tester->getDisplay();
    }

    private function contentOf(int $pageId): CmsPageContent
    {
        $content = CmsPageContentQuery::create()->filterByPageId($pageId)->filterByLocale($this->locale())->findOne();

        self::assertNotNull($content);

        return $content;
    }
}
