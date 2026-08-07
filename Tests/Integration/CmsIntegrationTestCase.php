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

namespace TheliaCMS\Tests\Integration;

use Thelia\Domain\Localization\Service\LangService;
use Thelia\Test\IntegrationTestCase;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Page\Admin\BuilderContent;
use TheliaCMS\Page\Admin\CmsPageWriter;
use TheliaCMS\Page\Admin\PageDraft;

/**
 * Shared ground for the integration tests of the module: a booted shop, a
 * transaction rolled back after each test, and pages created the way the back
 * office creates them.
 *
 * Pages go through CmsPageWriter rather than straight into the tables, because
 * publishing is what fills the rewritten URLs and the search index: fixtures
 * written by hand would test a shape of the data that never occurs.
 */
abstract class CmsIntegrationTestCase extends IntegrationTestCase
{
    /** @var list<int> ids of the pages created by createPage(), newest last */
    private array $createdPages = [];

    /**
     * Undoes by hand what the transaction cannot, for the test cases that run
     * without one — see PageSearchTest for why any of them do.
     */
    protected function tearDown(): void
    {
        if (!$this->useTransaction && [] !== $this->createdPages) {
            $connection = $this->getPropelConnection();
            $ids = implode(',', $this->createdPages);

            // The children of a page go with it (FK CASCADE), its URLs do not.
            $connection->exec(\sprintf("DELETE FROM rewriting_url WHERE view = 'cmspage' AND view_id IN (%s)", $ids));
            $connection->exec(\sprintf('DELETE FROM cms_page WHERE id IN (%s)', $ids));
        }

        $this->createdPages = [];

        parent::tearDown();
    }

    protected function writer(): CmsPageWriter
    {
        return $this->getService(CmsPageWriter::class);
    }

    /**
     * The language the module answers in here.
     *
     * Read rather than hardcoded: services resolve the locale of the visitor
     * through LangService, and a fixture written in another language makes them
     * return empty titles — which reads as a bug in the code under test.
     */
    protected function locale(): string
    {
        return $this->getService(LangService::class)->getLocale();
    }

    /**
     * A page with content, published in the given locale unless told otherwise.
     */
    protected function createPage(
        string $title,
        ?string $locale = null,
        int $parent = 0,
        ?string $html = null,
        bool $published = true,
        bool $visible = true,
    ): CmsPage {
        $locale ??= $this->locale();
        $page = new CmsPage();
        $page->setParent($parent)
            ->setPosition(0)
            ->setVisible($visible ? 1 : 0)
            ->setLayout('default');

        $writer = $this->writer();
        $writer->saveDraft($page, $locale, new PageDraft(title: $title));
        $writer->saveContent($page, $locale, new BuilderContent(
            projectData: '{"pages":[]}',
            html: $html ?? \sprintf('<h1>%s</h1>', htmlspecialchars($title)),
            css: '',
        ));

        if ($published) {
            $writer->publish($page, $locale);
        }

        $this->createdPages[] = (int) $page->getId();

        return $page;
    }
}
