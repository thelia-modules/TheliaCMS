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

use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\ConfigQuery;
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

    /**
     * Points the shop at a front theme it actually has.
     *
     * `bin/test-prepare` seeds the theme name of a stock install, which is not
     * necessarily the theme installed here: left alone, anything resolving a
     * parser works on a directory that does not exist, and the tests fail on
     * something that has nothing to do with what they measure. The write is
     * undone with the transaction of the test.
     */
    protected function useAnInstalledFrontTheme(): void
    {
        $helper = $this->getService(TemplateHelperInterface::class);

        if (is_dir($helper->getActiveFrontTemplate()->getAbsolutePath())) {
            return;
        }

        $installed = $helper->getList(TemplateDefinition::FRONT_OFFICE);

        if ([] === $installed) {
            self::markTestSkipped('The shop has no front theme installed, so no page can be rendered.');
        }

        ConfigQuery::write('active-front-template', $installed[0]->getName());
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
