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

use Symfony\Component\HttpKernel\KernelInterface;
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
    /** Checked once per run: the answer cannot change between two test cases. */
    private static ?string $checkedDatabase = null;

    /** @var list<int> ids of the pages created by createPage(), newest last */
    private array $createdPages = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->refuseToRunOnAnythingButTheTestDatabase();
    }

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
     * Stops the run when the tests are connected to anything but the database
     * `.env.test` names.
     *
     * Not a hypothetical. A container that exports `DATABASE_NAME` into the
     * shell wins over `.env.test`, because Dotenv does not overwrite variables
     * that are already set, and the Propel configuration is generated from
     * whichever value is in scope. The suite then runs against the *shop*
     * database, and the cases that deliberately work outside a transaction, the
     * module lifecycle among them, delete every CMS address of a live site and
     * rebuild it. That is how a real site lost its 124 addresses.
     *
     * `bin/test-prepare` clears those variables before generating the Propel
     * configuration, which is why the suite is normally fine. This is the guard
     * for the run where somebody purged `var/propel/test` and went straight to
     * phpunit.
     */
    private function refuseToRunOnAnythingButTheTestDatabase(): void
    {
        $connection = $this->getPropelConnection();

        self::$checkedDatabase ??= (string) $connection->query('SELECT DATABASE()')->fetchColumn();

        $declared = $this->databaseNamedInEnvTest();

        if (null === $declared || $declared === self::$checkedDatabase) {
            return;
        }

        self::fail(\sprintf(
            'These tests write outside a transaction and are connected to "%s", while .env.test names "%s". '
            .'Run `php bin/test-prepare` and check that nothing exports DATABASE_NAME into the shell, '
            .'the web_environment of a DDEV project for instance.',
            self::$checkedDatabase,
            $declared,
        ));
    }

    private function databaseNamedInEnvTest(): ?string
    {
        $file = $this->getService(KernelInterface::class)->getProjectDir().'/.env.test';

        if (!is_file($file)) {
            return null;
        }

        preg_match('/^\s*DATABASE_NAME\s*=\s*[\'"]?([^\'"\s#]+)/m', (string) file_get_contents($file), $matches);

        return $matches[1] ?? null;
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
