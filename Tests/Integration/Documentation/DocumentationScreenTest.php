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

namespace TheliaCMS\Tests\Integration\Documentation;

use Thelia\Core\Security\AccessManager;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * The documentation of the module, read by whoever edits the pages, in the
 * language of their back office.
 */
final class DocumentationScreenTest extends AdminScreenTestCase
{
    private const string SCREEN = '/admin/cms/documentation';

    /** What an editor is told about, whatever the language. */
    private const array TOPICS = ['pages', 'page-templates', 'components', 'skills', 'settings'];

    public function testTheSummaryListsEverySectionOfTheText(): void
    {
        $screen = $this->screen(self::SCREEN);

        $sections = $screen->filter('[data-testid="cms-documentation-text"] section[id]');
        $links = $screen->filter('[data-testid="cms-documentation-summary"] a');

        self::assertGreaterThanOrEqual(\count(self::TOPICS), $sections->count());
        self::assertSame($sections->count(), $links->count(), 'One entry of the summary per section.');

        foreach ($sections as $index => $section) {
            self::assertSame('#'.$section->getAttribute('id'), $links->eq($index)->attr('href'));
            self::assertSame(trim((string) $section->getElementsByTagName('h2')->item(0)?->textContent), trim($links->eq($index)->text()));
        }

        foreach (self::TOPICS as $topic) {
            self::assertSame(1, $screen->filter(\sprintf('section#%s', $topic))->count(), $topic.' is documented.');
        }
    }

    public function testTheSidebarOffersItAfterTheSettings(): void
    {
        $screen = $this->screen(self::SCREEN);

        $entry = $screen->filter('[data-testid="bo-nav-cms-documentation"]');

        self::assertSame(1, $entry->count(), 'The sidebar offers the documentation.');
        self::assertSame(self::SCREEN, parse_url((string) $entry->attr('href'), \PHP_URL_PATH));
        self::assertSame(0, $entry->closest('li')?->nextAll()->count(), 'It closes the CMS section.');
    }

    public function testABackOfficeSwitchedToFrenchReadsItInFrench(): void
    {
        // What the language switcher of the back office sends.
        $screen = $this->screen(self::SCREEN.'?lang=fr_FR');

        self::assertSame(1, $screen->filter('section#gabarits')->count(), 'The French text is served.');
        self::assertSame(0, $screen->filter('section#page-templates')->count());
    }

    public function testAnEditorWhoMaySeeThePagesReadsIt(): void
    {
        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([
            CmsResources::PAGE => [AccessManager::VIEW],
        ]));

        $this->screen(self::SCREEN);
    }

    public function testAProfileWithoutThePagesNeitherReadsNorIsOfferedIt(): void
    {
        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([
            CmsResources::MENU => [AccessManager::VIEW],
        ]));

        self::assertSame(403, $this->send('GET', self::SCREEN));

        $menus = $this->screen('/admin/cms/menus');
        self::assertSame(0, $menus->filter('[data-testid="bo-nav-cms-documentation"]')->count());
    }
}
