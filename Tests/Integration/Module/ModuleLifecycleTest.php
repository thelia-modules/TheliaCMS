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

use Thelia\Model\RewritingUrlQuery;
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

    public function testUpdatingFromThePreviousVersionAppliesItsMigration(): void
    {
        $migrations = TheliaCMS::migrationsBetween('0.0.0', '99.0.0');
        $latest = basename((string) end($migrations), '.sql');

        $module = new TheliaCMS();

        // Replayed on a shop that already has the tables: every migration file
        // is written to tolerate it, and a site updating from the previous
        // version is the only way most of them are ever run. The connection is
        // passed the way the core passes it.
        $module->update($this->previousVersionOf($latest), $latest, $this->getPropelConnection());

        self::assertTrue(
            $this->tableExists('cms_page_template'),
            'The table added by the last migration is missing after an update.',
        );
    }

    /**
     * The CMS addresses, ordered so two runs can be compared, of the given
     * pages or of the whole shop.
     *
     * @param list<int> $pageIds
     *
     * @return list<string>
     */
    private function cmsAddresses(array $pageIds = []): array
    {
        $query = RewritingUrlQuery::create()->filterByView(TheliaCMS::PAGE_VIEW);

        if ([] !== $pageIds) {
            $query->filterByViewId($pageIds);
        }

        $urls = $query->select(['Url'])->find()->toArray();

        $urls = array_values(array_map(strval(...), $urls));
        sort($urls);

        return $urls;
    }

    private function previousVersionOf(string $version): string
    {
        $parts = explode('.', $version);
        $parts[1] = (string) max(0, ((int) ($parts[1] ?? 0)) - 1);

        return implode('.', $parts);
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->getPropelConnection()->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $statement->execute(['table' => $table]);

        return (int) $statement->fetchColumn() > 0;
    }
}
