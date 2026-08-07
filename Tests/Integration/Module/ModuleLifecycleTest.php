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

namespace TheliaCMS\Tests\Integration\Module;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\RewritingUrlQuery;
use TheliaCMS\Page\Admin\PageDraft;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\TheliaCMS;

/**
 * Turning the module off and on again.
 *
 * The addresses of the pages live in a core table that outlives the module.
 * Left behind, they keep routing visitors to a view nothing can render, which
 * is a 500 and not a 404; dropped and never rebuilt, every page of the site is
 * gone after a deactivation somebody undid a minute later.
 *
 * Runs outside a transaction: the module writes through its own connection and
 * the migrations it replays are schema statements, which commit on their own in
 * MySQL. What the test creates is undone by hand.
 */
final class ModuleLifecycleTest extends CmsIntegrationTestCase
{
    protected bool $useTransaction = false;

    public function testDeactivationTakesTheAddressesDownAndActivationPutsThemBack(): void
    {
        $page = $this->createPage('Page en ligne');
        $child = $this->createPage('Page rangée dessous', parent: (int) $page->getId());
        $ids = [(int) $page->getId(), (int) $child->getId()];

        $before = $this->cmsAddresses($ids);

        self::assertContains('page-en-ligne', $before);
        self::assertContains('page-en-ligne/page-rangee-dessous', $before, 'A child page is addressed under its parent.');

        $module = new TheliaCMS();
        $module->preDeactivation();

        self::assertSame(
            [],
            $this->cmsAddresses(),
            'A CMS address left behind routes to a view the deactivated module cannot render, which is a 500.',
        );

        $module->postActivation();

        self::assertSame($before, $this->cmsAddresses($ids), 'The addresses are rebuilt from the pages, exactly as they were.');
    }

    /**
     * The address of a page is not always derivable from its title: it is
     * editable, and an author who shortens `/le-groupe-et-son-histoire` into
     * `/groupe` expects that address to be the one the site keeps.
     *
     * A cycle that rebuilds the addresses from the titles renames every page
     * that was ever renamed, and leaves no redirection behind: the indexed
     * addresses of the site are gone. The children go with them, since their
     * path is prefixed by the address of their parent.
     */
    public function testAnAddressEditedByHandSurvivesTheCycle(): void
    {
        $page = $this->createPage('Le groupe et son histoire');
        $this->writer()->saveDraft($page, $this->locale(), new PageDraft(title: 'Le groupe et son histoire', slug: 'groupe'));

        $child = $this->createPage('Nos engagements', parent: (int) $page->getId());
        $ids = [(int) $page->getId(), (int) $child->getId()];

        $before = $this->cmsAddresses($ids);

        self::assertContains('groupe', $before, 'The address asked for is the one in place.');
        self::assertContains('groupe/nos-engagements', $before, 'A child hangs under the address of its parent.');

        $module = new TheliaCMS();
        $module->preDeactivation();
        $module->postActivation();

        self::assertSame($before, $this->cmsAddresses($ids), 'The addresses come back as they were, edited slugs included.');
    }

    /**
     * A child created before its parent, which is what re-parenting produces.
     *
     * The addresses are rebuilt page by page, in the order the rows come back,
     * so a child can be reached before the parent it hangs under has an address
     * again. Its prefix then has to come from the slug stored on the parent: the
     * title of the parent is a different string as soon as anybody edited its
     * address, and the child would land somewhere else entirely.
     */
    public function testAChildRebuiltBeforeItsParentStillLandsUnderIt(): void
    {
        $child = $this->createPage('Nos engagements');
        $parent = $this->createPage('Le groupe et son histoire');

        self::assertLessThan((int) $parent->getId(), (int) $child->getId(), 'The child has to be reached first for this to measure anything.');

        $this->writer()->saveDraft($parent, $this->locale(), new PageDraft(title: 'Le groupe et son histoire', slug: 'groupe'));

        $child->setParent((int) $parent->getId());
        $this->writer()->saveDraft($child, $this->locale(), new PageDraft(title: 'Nos engagements', slug: 'nos-engagements'));

        $ids = [(int) $child->getId(), (int) $parent->getId()];
        $before = $this->cmsAddresses($ids);

        self::assertSame(['groupe', 'groupe/nos-engagements'], $before);

        $module = new TheliaCMS();
        $module->preDeactivation();
        $module->postActivation();

        self::assertSame($before, $this->cmsAddresses($ids), 'The child hangs under the address of its parent, whichever is rebuilt first.');
    }

    /**
     * A site updating from before the first release receives every migration.
     *
     * Run from `0.0.0` rather than from a version computed by decrementing the
     * latest one: the module ships pre-release versions, which no arithmetic on
     * the parts of the number produces, and a range that selects no file at all
     * makes this test pass without applying anything.
     */
    public function testUpdatingFromAnOlderVersionAppliesTheMigrationsItMissed(): void
    {
        $migrations = TheliaCMS::migrationsBetween('0.0.0', '99.0.0');
        $latest = basename((string) end($migrations), '.sql');

        // Replayed on a shop that already has the tables and the columns: every
        // migration file is written to tolerate it. The connection is passed the
        // way the core passes it.
        (new TheliaCMS())->update('0.0.0', $latest, $this->getPropelConnection());

        self::assertTrue(
            $this->tableExists('cms_page_template'),
            'A table added by a migration is missing after an update.',
        );

        self::assertTrue(
            $this->columnExists('cms_page_i18n', 'slug'),
            'The column holding the address of a page is missing after an update, so a deactivation would rebuild the addresses from the titles.',
        );
    }

    /**
     * The CMS addresses in use, ordered so two runs can be compared, of the
     * given pages or of the whole shop.
     *
     * The rows kept behind a rename are left out: they are 301s, and the module
     * stores the address a page answers on, not the list of the ones it used to
     * answer on, so a cycle does not bring them back. That limit is reported,
     * and reading it here as a promise would be wrong.
     *
     * @param list<int> $pageIds
     *
     * @return list<string>
     */
    private function cmsAddresses(array $pageIds = []): array
    {
        $query = RewritingUrlQuery::create()
            ->filterByView(TheliaCMS::PAGE_VIEW)
            ->filterByRedirected(null, Criteria::ISNULL);

        if ([] !== $pageIds) {
            $query->filterByViewId($pageIds);
        }

        $urls = $query->select(['Url'])->find()->toArray();

        $urls = array_values(array_map(strval(...), $urls));
        sort($urls);

        return $urls;
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->getPropelConnection()->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $statement->execute(['table' => $table]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->getPropelConnection()->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
        );
        $statement->execute(['table' => $table, 'column' => $column]);

        return (int) $statement->fetchColumn() > 0;
    }
}
