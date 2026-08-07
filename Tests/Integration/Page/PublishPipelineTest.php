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

use Thelia\Model\RewritingUrlQuery;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageRevisionQuery;
use TheliaCMS\Model\CmsPageSearchQuery;
use TheliaCMS\Page\Admin\EmptyPageContentException;
use TheliaCMS\Page\Admin\PageDraft;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * Publishing writes to four tables and has to leave the four of them agreeing
 * with each other: the snapshot a visitor is served, the text the search page
 * looks through, the revision to fall back on, and the address the page answers
 * at.
 */
final class PublishPipelineTest extends CmsIntegrationTestCase
{
    public function testPublishingWritesTheSnapshotTheSearchTextAndARevision(): void
    {
        $page = $this->createPage('Conseil et accompagnement', html: '<h1>Conseil</h1><p>Un accompagnement sur mesure.</p>');
        $id = (int) $page->getId();

        $content = CmsPageContentQuery::create()->filterByPageId($id)->filterByLocale($this->locale())->findOne();

        self::assertNotNull($content);
        self::assertNotNull($content->getPublishedAt());
        self::assertStringContainsString('Un accompagnement sur mesure.', (string) $content->getPublishedHtml());

        $index = CmsPageSearchQuery::create()->filterByPageId($id)->filterByLocale($this->locale())->findOne();

        self::assertNotNull($index, 'A published page the search page cannot find.');
        self::assertStringContainsString('accompagnement', (string) $index->getContent());

        self::assertSame(
            1,
            CmsPageRevisionQuery::create()->filterByPageId($id)->filterByLocale($this->locale())->count(),
            'Publishing takes a snapshot to fall back on.',
        );

        self::assertSame(
            1,
            RewritingUrlQuery::create()->filterByView('cmspage')->filterByViewId($id)->filterByViewLocale($this->locale())->count(),
        );
    }

    public function testAPageWithNothingOnItIsNotPublished(): void
    {
        $page = $this->pageWithoutContent('Page en chantier');

        $this->expectException(EmptyPageContentException::class);

        try {
            $this->writer()->publish($page, $this->locale());
        } finally {
            $content = CmsPageContentQuery::create()
                ->filterByPageId($page->getId())
                ->filterByLocale($this->locale())
                ->findOne();

            // Were the date written without the HTML, the back office would
            // read "published" and the visitor would get a 404.
            self::assertNull($content?->getPublishedAt());
            self::assertNull($content?->getPublishedHtml());
        }
    }

    public function testRefusingToPublishLeavesNoTraceBehind(): void
    {
        $page = $this->pageWithoutContent('Page en chantier');
        $id = (int) $page->getId();

        try {
            $this->writer()->publish($page, $this->locale());
        } catch (EmptyPageContentException) {
            // The point of the test is what the rolled back transaction left.
        }

        self::assertSame(0, CmsPageRevisionQuery::create()->filterByPageId($id)->count());
        self::assertSame(0, CmsPageSearchQuery::create()->filterByPageId($id)->count());
    }

    public function testAPageHoldingOnlyABlankParagraphIsNotPublished(): void
    {
        $page = $this->createPage('Page vide', html: '<div class="cms-page-content"><p>&nbsp;</p></div>', published: false);

        $this->expectException(EmptyPageContentException::class);

        $this->writer()->publish($page, $this->locale());
    }

    /**
     * A page created and saved, whose editor was never opened: the row in
     * cms_page_content exists, with no draft in it.
     */
    private function pageWithoutContent(string $title): CmsPage
    {
        $page = new CmsPage();
        $page->setParent(0)
            ->setPosition(0)
            ->setVisible(1)
            ->setLayout('default');

        $this->writer()->saveDraft($page, $this->locale(), new PageDraft(title: $title));

        return $page;
    }
}
