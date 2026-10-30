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

namespace TheliaCMS\Tests\Integration\Menu;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Thelia\Core\Content\Slot\ContentSlotLink;
use Thelia\Core\Content\Slot\ContentSlots;
use Thelia\Model\LangQuery;
use TheliaCMS\ImportExport\ImportOptions;
use TheliaCMS\ImportExport\SiteDocument;
use TheliaCMS\ImportExport\SiteExporter;
use TheliaCMS\ImportExport\SiteImporter;
use TheliaCMS\Menu\Admin\CmsMenuRepository;
use TheliaCMS\Menu\Admin\CmsMenuWriter;
use TheliaCMS\Menu\CmsContentSlotResolver;
use TheliaCMS\Menu\CmsMenuProvider;
use TheliaCMS\Menu\MenuCache;
use TheliaCMS\Model\CmsMenu;
use TheliaCMS\Model\CmsMenuItem;
use TheliaCMS\Model\CmsMenuItemQuery;
use TheliaCMS\Model\CmsMenuQuery;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * A language may have a menu of its own beside the shared one: what a visitor
 * reads in each language, and what detaching, realigning and sending a
 * language back do to the rows.
 */
final class LocaleMenuTest extends CmsIntegrationTestCase
{
    private const string CODE = 'locale-menu-test';

    private string $other;

    protected function setUp(): void
    {
        parent::setUp();

        $other = LangQuery::create()->filterByActive(1)->filterByLocale($this->locale(), Criteria::NOT_EQUAL)->findOne();

        if (null === $other) {
            self::markTestSkipped('A second active language is needed.');
        }

        $this->other = (string) $other->getLocale();
    }

    public function testALanguageWithoutAMenuOfItsOwnReadsTheSharedOne(): void
    {
        $this->sharedMenu(['Accueil', 'Contact']);

        self::assertSame(['Accueil', 'Contact'], $this->labels($this->locale()));
        self::assertSame(['Accueil', 'Contact'], $this->labels($this->other));
    }

    public function testADetachedLanguageReadsItsOwnMenuAndTheOthersTheShared(): void
    {
        $this->sharedMenu(['Accueil', 'Contact']);
        $detached = $this->menuWriter()->detach(self::CODE, $this->other);
        CmsMenuItemQuery::create()->filterByMenuId($detached->getId())->orderByPosition()->findOne()?->delete();
        $this->getService(MenuCache::class)->invalidate();

        self::assertSame(['Contact'], $this->labels($this->other));
        self::assertSame(['Accueil', 'Contact'], $this->labels($this->locale()));
    }

    public function testAnEmptiedDetachedMenuStaysEmptyAndDoesNotShowTheShared(): void
    {
        $this->sharedMenu(['Accueil']);
        $detached = $this->menuWriter()->detach(self::CODE, $this->other);
        CmsMenuItemQuery::create()->filterByMenuId($detached->getId())->delete();
        $this->getService(MenuCache::class)->invalidate();

        self::assertSame([], $this->labels($this->other));
        self::assertFalse($this->provider()->hasEntries(self::CODE, $this->other));
        self::assertTrue($this->provider()->hasEntries(self::CODE, $this->locale()), 'The count is kept per language: one cached answer for all of them would leak.');
    }

    /**
     * The slot of the core is asked in a language: a language whose own menu
     * was emptied gives the slot back to the shop in that language only, as
     * an empty shared menu does everywhere.
     */
    public function testTheHeaderSlotFollowsTheMenuOfEachLanguage(): void
    {
        $main = CmsMenuQuery::findShared('main');
        self::assertInstanceOf(CmsMenu::class, $main, 'The seeder creates the main menu; without it this test measures nothing.');
        CmsMenuItemQuery::create()->filterByMenuId($main->getId())->delete();
        $this->entry($main, 'Accueil', 1);
        $detached = $this->menuWriter()->detach('main', $this->other);
        CmsMenuItemQuery::create()->filterByMenuId($detached->getId())->delete();
        $this->getService(MenuCache::class)->invalidate();

        $slots = $this->getService(CmsContentSlotResolver::class);

        self::assertNull($slots->resolve(ContentSlots::HEADER, $this->other));
        self::assertSame(['Accueil'], array_map(
            static fn (ContentSlotLink $link): string => $link->label,
            $slots->resolve(ContentSlots::HEADER, $this->locale()) ?? [],
        ));
    }

    public function testDetachingCopiesTheTitlesTheEntriesAndTheirLabelsInEveryLanguage(): void
    {
        $shared = $this->sharedMenu([]);
        $shared->setLocale($this->other)->setTitle('Other title')->save();
        $parent = $this->entry($shared, 'Parent', 1);
        // Stored before its parent on purpose: a copy in id order would file
        // it under nothing if parents were not remapped afterwards.
        $child = $this->entry($shared, 'Enfant', 1);
        $grandParent = $this->entry($shared, 'Rubrique', 2);
        $parent->setParent((int) $grandParent->getId())->save();
        $child->setParent((int) $parent->getId())->save();
        $child->setLocale($this->other)->setLabel('Child')->save();

        $detached = $this->menuWriter()->detach(self::CODE, $this->other);

        self::assertSame($this->other, $detached->getMenuLocale());
        self::assertSame('Other title', $detached->setLocale($this->other)->getTitle());
        self::assertSame('Menu de test', $detached->setLocale($this->locale())->getTitle());

        $copies = [];
        foreach (CmsMenuItemQuery::create()->filterByMenuId($detached->getId())->find() as $copy) {
            $copies[(string) $copy->setLocale($this->locale())->getLabel()] = $copy;
        }

        self::assertSame(['Enfant', 'Parent', 'Rubrique'], array_keys($this->sorted($copies)));
        self::assertSame((int) $copies['Parent']->getId(), (int) $copies['Enfant']->getParent());
        self::assertSame((int) $copies['Rubrique']->getId(), (int) $copies['Parent']->getParent());
        self::assertSame(0, (int) $copies['Rubrique']->getParent());
        self::assertSame('Child', $copies['Enfant']->setLocale($this->other)->getLabel());
        self::assertSame(3, CmsMenuItemQuery::create()->filterByMenuId($shared->getId())->count(), 'The shared menu keeps its entries.');
    }

    public function testRealigningALanguageReplacesItsEntriesByTheShared(): void
    {
        $this->sharedMenu(['Accueil', 'Contact']);
        $detached = $this->menuWriter()->detach(self::CODE, $this->other);
        $detached->setLocale($this->other)->setTitle('Own title')->save();
        $this->entry($detached, 'Ajout', 9);
        self::assertSame(['target' => 3, 'source' => 2], $this->getService(CmsMenuRepository::class)->realignCounts(self::CODE, $this->other, ''));

        $this->menuWriter()->realign(self::CODE, $this->other, '');

        self::assertSame(['Accueil', 'Contact'], $this->labels($this->other));
        self::assertSame('Own title', CmsMenuQuery::findForLocale(self::CODE, $this->other)?->setLocale($this->other)->getTitle(), 'The titles of the target stay.');
    }

    public function testRealigningTheSharedMenuOnALanguage(): void
    {
        $this->sharedMenu(['Accueil']);
        $detached = $this->menuWriter()->detach(self::CODE, $this->other);
        $this->entry($detached, 'Nouveau', 2);

        $this->menuWriter()->realign(self::CODE, '', $this->other);

        self::assertSame(['Accueil', 'Nouveau'], $this->labels($this->locale()));
        self::assertSame(2, CmsMenuItemQuery::create()->filterByMenuId($detached->getId())->count(), 'The source is left as it was.');
    }

    public function testSendingALanguageBackDeletesItsMenu(): void
    {
        $this->sharedMenu(['Accueil']);
        $detached = $this->menuWriter()->detach(self::CODE, $this->other);
        $id = (int) $detached->getId();

        $this->menuWriter()->reattach(self::CODE, $this->other);

        self::assertNull(CmsMenuQuery::findForLocale(self::CODE, $this->other));
        self::assertSame(0, CmsMenuItemQuery::create()->filterByMenuId($id)->count());
        self::assertSame(['Accueil'], $this->labels($this->other));
    }

    public function testRefusals(): void
    {
        $this->sharedMenu(['Accueil']);
        $inactive = LangQuery::create()->filterByActive(0)->findOne();

        $this->assertRefused(fn () => $this->menuWriter()->detach(self::CODE, ''), 'Pick the language to give a menu of its own.');
        $this->assertRefused(fn () => $this->menuWriter()->detach('no-such-menu', $this->other), 'There is no menu with this code.');
        $this->assertRefused(fn () => $this->menuWriter()->realign(self::CODE, $this->other, ''), 'This language has no menu of its own: detach it first.');
        $this->assertRefused(fn () => $this->menuWriter()->reattach(self::CODE, $this->other), 'This language already reads the shared menu.');
        $this->assertRefused(fn () => $this->menuWriter()->reattach(self::CODE, ''), 'The shared menu cannot be sent back to itself.');

        if (null !== $inactive) {
            $this->assertRefused(fn () => $this->menuWriter()->detach(self::CODE, (string) $inactive->getLocale()), 'This language is not active on the shop.');
        }

        $this->menuWriter()->detach(self::CODE, $this->other);

        $this->assertRefused(fn () => $this->menuWriter()->detach(self::CODE, $this->other), 'This language already has a menu of its own.');
        $this->assertRefused(fn () => $this->menuWriter()->realign(self::CODE, $this->other, $this->other), 'A menu cannot be realigned on itself.');
        $this->assertRefused(fn () => $this->menuWriter()->realign(self::CODE, $this->other, 'xx_XX'), 'The menu to copy from does not exist.');
    }

    public function testRenamingTheSharedMenuRenamesItsLanguagesAndTheListShowsItOnce(): void
    {
        $shared = $this->sharedMenu(['Accueil']);
        $this->menuWriter()->detach(self::CODE, $this->other);

        $this->menuWriter()->saveMenu($shared, $this->locale(), self::CODE.'-renamed', 'Menu de test');

        self::assertNotNull(CmsMenuQuery::findForLocale(self::CODE.'-renamed', $this->other));
        self::assertNull(CmsMenuQuery::findForLocale(self::CODE, $this->other));

        $listed = array_values(array_filter(
            $this->getService(CmsMenuRepository::class)->menus($this->locale()),
            static fn (array $summary): bool => self::CODE.'-renamed' === $summary['menu']->getCode(),
        ));

        self::assertCount(1, $listed);
        self::assertSame([$this->other], $listed[0]['locales']);
    }

    public function testTheDatabaseHoldsOneMenuPerCodeAndLanguage(): void
    {
        $this->sharedMenu([]);

        $this->expectException(PropelException::class);

        (new CmsMenu())->setCode(self::CODE)->save();
    }

    public function testAnExportedLanguageMenuComesBackAsSuch(): void
    {
        $this->sharedMenu(['Accueil']);
        $detached = $this->menuWriter()->detach(self::CODE, $this->other);
        $this->entry($detached, 'Seulement ici', 2);
        $file = $this->getService(SiteExporter::class)->export();

        $menus = array_values(array_filter($file['menus'], static fn (array $menu): bool => self::CODE === $menu['code']));
        self::assertSame(['', $this->other], array_column($menus, 'locale'));

        $this->menuWriter()->reattach(self::CODE, $this->other);
        CmsMenuQuery::findShared(self::CODE)?->delete();

        $this->getService(SiteImporter::class)->import(SiteDocument::fromArray($file), new ImportOptions());
        $this->getService(MenuCache::class)->invalidate();

        self::assertSame(['Accueil'], $this->labels($this->locale()));
        self::assertSame(['Accueil', 'Seulement ici'], $this->labels($this->other));
    }

    public function testAFileWithoutLocalesImportsItsMenusAsShared(): void
    {
        $file = $this->getService(SiteExporter::class)->export();
        $file['menus'] = [['code' => self::CODE, 'translations' => [], 'items' => []]];

        $this->getService(SiteImporter::class)->import(SiteDocument::fromArray($file), new ImportOptions());

        self::assertNotNull(CmsMenuQuery::findShared(self::CODE));
    }

    public function testTheMenuOfALanguageTheSiteLacksOrWithoutItsSharedMenuIsLeftOut(): void
    {
        $file = $this->getService(SiteExporter::class)->export();
        $file['menus'] = [
            ['code' => self::CODE, 'locale' => 'zz_ZZ', 'translations' => [], 'items' => []],
            ['code' => self::CODE, 'translations' => [], 'items' => []],
            ['code' => self::CODE.'-orphan', 'locale' => $this->other, 'translations' => [], 'items' => []],
        ];

        $report = $this->getService(SiteImporter::class)->import(SiteDocument::fromArray($file), new ImportOptions());

        self::assertNotNull(CmsMenuQuery::findShared(self::CODE), 'The shared menu is taken in first, whatever the order of the file.');
        self::assertNull(CmsMenuQuery::findForLocale(self::CODE, 'zz_ZZ'));
        self::assertSame(0, CmsMenuQuery::create()->filterByCode(self::CODE.'-orphan')->count());
        self::assertCount(2, array_filter($report->warnings(), static fn (string $warning): bool => str_contains($warning, 'was left out')));
    }

    public function testDeletingTheSharedMenuDeletesTheMenusOfItsLanguages(): void
    {
        $shared = $this->sharedMenu(['Accueil']);
        $detached = $this->menuWriter()->detach(self::CODE, $this->other);
        $detachedId = (int) $detached->getId();

        $this->menuWriter()->deleteMenu($shared);

        self::assertSame(0, CmsMenuQuery::create()->filterByCode(self::CODE)->count());
        self::assertSame(0, CmsMenuItemQuery::create()->filterByMenuId($detachedId)->count());
    }

    public function testReplacingASharedMenuWarnsAboutTheLanguagesKeepingTheirOwn(): void
    {
        $this->sharedMenu(['Accueil']);
        $this->menuWriter()->detach(self::CODE, $this->other);
        $file = $this->getService(SiteExporter::class)->export();
        $menus = array_values(array_filter($file['menus'], static fn (array $menu): bool => self::CODE === $menu['code']));
        $kept = static fn ($report): array => array_values(array_filter($report->warnings(), static fn (string $warning): bool => str_contains($warning, 'keep a menu of their own')));

        $file['menus'] = [$menus[0]];
        $report = $this->getService(SiteImporter::class)->import(SiteDocument::fromArray($file), new ImportOptions(replace: true));
        self::assertCount(1, $kept($report));
        self::assertStringContainsString(self::CODE, $kept($report)[0]);
        self::assertStringContainsString($this->other, $kept($report)[0]);

        $file['menus'] = $menus;
        self::assertSame([], $kept($this->getService(SiteImporter::class)->import(SiteDocument::fromArray($file), new ImportOptions(replace: true))), 'The file carries the language: nothing is kept.');

        $file['menus'] = [$menus[0]];
        self::assertSame([], $kept($this->getService(SiteImporter::class)->import(SiteDocument::fromArray($file), new ImportOptions())), 'Without replace the menu is left alone.');
    }

    /**
     * @param list<string> $labels
     */
    private function sharedMenu(array $labels): CmsMenu
    {
        $menu = (new CmsMenu())->setCode(self::CODE);
        $menu->setLocale($this->locale())->setTitle('Menu de test');
        $menu->save();

        foreach ($labels as $index => $label) {
            $this->entry($menu, $label, $index + 1);
        }

        return $menu;
    }

    private function entry(CmsMenu $menu, string $label, int $position): CmsMenuItem
    {
        $item = (new CmsMenuItem())
            ->setMenuId($menu->getId())
            ->setParent(0)
            ->setPosition($position)
            ->setTargetType('url')
            ->setUrl('https://example.com/'.rawurlencode($label));
        $item->setLocale($this->locale())->setLabel($label);
        $item->setLocale($this->other)->setLabel($label);
        $item->save();

        $this->getService(MenuCache::class)->invalidate();

        return $item;
    }

    /**
     * @return list<string>
     */
    private function labels(string $locale): array
    {
        return array_map(static fn (array $node): string => (string) $node['label'], $this->provider()->menu(self::CODE, $locale));
    }

    /**
     * @param array<string, CmsMenuItem> $copies
     *
     * @return array<string, CmsMenuItem>
     */
    private function sorted(array $copies): array
    {
        ksort($copies);

        return $copies;
    }

    private function assertRefused(callable $call, string $message): void
    {
        try {
            $call();
        } catch (\DomainException $exception) {
            self::assertSame($message, $exception->getMessage());

            return;
        }

        self::fail('Not refused: '.$message);
    }

    private function menuWriter(): CmsMenuWriter
    {
        return $this->getService(CmsMenuWriter::class);
    }

    private function provider(): CmsMenuProvider
    {
        return $this->getService(CmsMenuProvider::class);
    }
}
