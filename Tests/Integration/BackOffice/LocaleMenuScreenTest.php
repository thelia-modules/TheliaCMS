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

namespace TheliaCMS\Tests\Integration\BackOffice;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\LangQuery;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Menu\Admin\CmsMenuRepository;
use TheliaCMS\Menu\Admin\CmsMenuWriter;
use TheliaCMS\Menu\MenuCache;
use TheliaCMS\Model\CmsMenu;
use TheliaCMS\Model\CmsMenuItem;
use TheliaCMS\Model\CmsMenuItemQuery;
use TheliaCMS\Model\CmsMenuQuery;
use TheliaCMS\Model\Map\CmsMenuItemTableMap;
use TheliaCMS\Model\Map\CmsMenuTableMap;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * The menu screens for a menu per language: the tree shown is the one of the
 * language being edited, and giving a language a menu of its own, realigning
 * it and sending it back to the shared menu each ask for the token, the right
 * to change menus and, when entries are replaced or deleted, a confirmation
 * that says how many.
 */
final class LocaleMenuScreenTest extends AdminScreenTestCase
{
    private const string CODE = 'locale-menu-screen';

    private int $otherId;

    private string $other;

    protected function setUp(): void
    {
        parent::setUp();

        $other = LangQuery::create()->filterByActive(1)->filterByLocale($this->locale(), Criteria::NOT_EQUAL)->findOne();

        if (null === $other) {
            self::markTestSkipped('A second active language is needed.');
        }

        $this->other = (string) $other->getLocale();
        $this->otherId = (int) $other->getId();
    }

    public function testTheListShowsOneRowPerCodeAndTheLanguagesWithAMenuOfTheirOwn(): void
    {
        $shared = $this->sharedMenu(['Accueil']);
        $detached = $this->detach($shared);

        $list = $this->screen('/admin/cms/menus');

        self::assertSame(1, $list->filter(\sprintf('[data-testid="cms-menu-row-%d"]', $shared->getId()))->count());
        self::assertSame(0, $list->filter(\sprintf('[data-testid="cms-menu-row-%d"]', $detached->getId()))->count(), 'A detached menu has no row of its own.');
        self::assertSame(1, $list->filter(\sprintf('[data-testid="cms-menu-locale-%d-%s"]', $shared->getId(), $this->other))->count());
    }

    public function testTheScreenEditsTheRowOfTheLanguageBeingEdited(): void
    {
        $shared = $this->sharedMenu(['Accueil', 'Contact']);
        $detached = $this->detach($shared);
        $this->entry($detached, 'Seulement ici', 3);

        $sharedScreen = $this->screen($this->url($shared, $this->languageId()));
        self::assertSame(1, $sharedScreen->filter('[data-testid="cms-menu-status-shared"]')->count());
        self::assertSame(2, $sharedScreen->filter('tr[data-row-id]')->count());
        self::assertStringNotContainsString('Seulement ici', $sharedScreen->text());

        $ownScreen = $this->screen($this->url($detached, $this->otherId));
        self::assertSame(1, $ownScreen->filter('[data-testid="cms-menu-status-detached"]')->count());
        self::assertSame(3, $ownScreen->filter('tr[data-row-id]')->count());
        self::assertStringContainsString('Seulement ici', $ownScreen->text());
    }

    public function testAGetOnAnotherRowOfTheCodeIsRedirectedToTheRowOfTheLanguage(): void
    {
        $shared = $this->sharedMenu(['Accueil']);
        $detached = $this->detach($shared);

        self::assertSame(302, $this->send('GET', $this->url($shared, $this->otherId)));
        self::assertStringEndsWith($this->url($detached, $this->otherId), (string) $this->client->getResponse()->headers->get('Location'));

        self::assertSame(302, $this->send('GET', $this->url($detached, $this->languageId())));
        self::assertStringEndsWith($this->url($shared, $this->languageId()), (string) $this->client->getResponse()->headers->get('Location'));

        self::assertSame(200, $this->send('GET', $this->url($detached, $this->otherId)));
        self::assertSame(200, $this->send('GET', $this->url($shared, $this->languageId())));
    }

    public function testDetachingNeedsTheTokenAndTheRightToChangeMenus(): void
    {
        $shared = $this->sharedMenu(['Accueil']);
        $action = \sprintf('/admin/cms/menus/%d/detach', $shared->getId());

        self::assertSame(403, $this->send('POST', $action.'?edit_language_id='.$this->otherId));
        self::assertNull(CmsMenuQuery::findForLocale(self::CODE, $this->other), 'Refused without the token.');

        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([CmsResources::MENU => [AccessManager::VIEW]]));
        $screen = $this->screen($this->url($shared, $this->languageId()));
        self::assertSame(0, $screen->filter('[data-testid="cms-menu-detach"]')->count(), 'No button for who cannot use it.');

        $token = $this->tokenOfTheScripts($screen);
        self::assertSame(403, $this->send('POST', $action.'?edit_language_id='.$this->otherId, ['_token' => $token]));
        self::assertNull(CmsMenuQuery::findForLocale(self::CODE, $this->other), 'Refused without the right.');
    }

    public function testDetachingCopiesTheSharedMenuForTheLanguageBeingEdited(): void
    {
        $shared = $this->sharedMenu(['Accueil', 'Contact']);
        $action = \sprintf('/admin/cms/menus/%d/detach?edit_language_id=%d', $shared->getId(), $this->otherId);

        $screen = $this->screen($this->url($shared, $this->otherId));
        $token = $this->tokenOfTheForm($screen, parse_url($action, \PHP_URL_PATH));

        self::assertSame(302, $this->send('POST', $action, ['_token' => $token]));

        $detached = CmsMenuQuery::findForLocale(self::CODE, $this->other);
        self::assertInstanceOf(CmsMenu::class, $detached);
        self::assertSame(2, $this->entries($detached));
        self::assertStringEndsWith($this->url($detached, $this->otherId), (string) $this->client->getResponse()->headers->get('Location'));

        $after = $this->screen($this->url($detached, $this->otherId));
        self::assertSame(1, $after->filter('[data-testid="cms-menu-status-detached"]')->count());
        self::assertSame(0, $after->filter('[data-testid="cms-menu-detach"]')->count());
        self::assertSame(1, $after->filter('input[name="cms_menu[code]"][disabled]')->count(), 'The code is locked on a detached menu.');
        self::assertSame(2, $this->entries($shared));
    }

    public function testRealignAsksForConfirmationWithTheCountsThenReplacesTheEntries(): void
    {
        $shared = $this->sharedMenu(['Accueil', 'Contact', 'Aide']);
        $detached = $this->detach($shared);
        CmsMenuItemQuery::create()->filterByMenuId($detached->getId())->limit(1)->find()->delete();
        $this->forgetMenus();
        self::assertSame(2, $this->entries($detached));

        $confirm = $this->screen(\sprintf('/admin/cms/menus/%d/realign?source=shared&edit_language_id=%d', $detached->getId(), $this->otherId));
        self::assertStringContainsString('2', $confirm->filter('[data-testid="cms-menu-confirm-summary"]')->text());
        self::assertStringContainsString('3', $confirm->filter('[data-testid="cms-menu-confirm-summary"]')->text());
        self::assertSame(2, $this->entries($detached), 'Asking changes nothing.');

        $action = \sprintf('/admin/cms/menus/%d/realign', $detached->getId());
        $token = $this->tokenOfTheForm($confirm, $action);

        self::assertSame(403, $this->send('POST', $action, ['source' => 'shared']));
        self::assertSame(2, $this->entries($detached), 'Refused without the token.');

        self::assertSame(302, $this->send('POST', $action, ['source' => 'shared', '_token' => $token]));
        self::assertSame(3, $this->entries($detached));
    }

    public function testTheSharedMenuCanBeRealignedOnALanguage(): void
    {
        $shared = $this->sharedMenu(['Accueil']);
        $detached = $this->detach($shared);
        $this->entry($detached, 'Un', 2);
        $this->entry($detached, 'Deux', 3);

        $screen = $this->screen($this->url($detached, $this->otherId));
        $link = $screen->filter('[data-testid="cms-menu-realign-shared"]');
        self::assertSame(1, $link->count());

        $confirm = $this->screen((string) $link->attr('href'));
        $action = \sprintf('/admin/cms/menus/%d/realign', $shared->getId());
        $token = $this->tokenOfTheForm($confirm, $action);

        self::assertSame(302, $this->send('POST', $action, ['source' => $this->other, '_token' => $token]));
        self::assertSame(3, $this->entries($shared));
    }

    public function testARealignmentRefusedGoesBackToTheScreenWithAMessage(): void
    {
        $shared = $this->sharedMenu(['Accueil']);
        $detached = $this->detach($shared);
        $base = \sprintf('/admin/cms/menus/%d/realign', $detached->getId());

        foreach (['', '?source='.$this->other, '?source=shared-not', '?source=fr_XX'] as $query) {
            self::assertSame(302, $this->send('GET', $base.$query), 'Refused: '.$query);
            self::assertStringEndsWith($this->url($detached, $this->languageId()), (string) $this->client->getResponse()->headers->get('Location'));
        }

        $token = $this->tokenOfTheForm($this->screen($this->url($detached, $this->otherId)), '/admin/cms/menus/'.$detached->getId());
        self::assertSame(302, $this->send('POST', $base, ['source' => $this->other, '_token' => $token]), 'A menu cannot be realigned on itself.');
        self::assertSame(1, $this->entries($detached));
    }

    public function testGoingBackToTheSharedMenuAsksFirstThenDeletesTheMenuOfTheLanguage(): void
    {
        $shared = $this->sharedMenu(['Accueil', 'Contact']);
        $detached = $this->detach($shared);
        $detachedId = (int) $detached->getId();

        $confirm = $this->screen(\sprintf('/admin/cms/menus/%d/reattach?edit_language_id=%d', $detachedId, $this->otherId));
        self::assertStringContainsString('2', $confirm->filter('[data-testid="cms-menu-confirm-summary"]')->text());
        self::assertInstanceOf(CmsMenu::class, CmsMenuQuery::findForLocale(self::CODE, $this->other), 'Asking changes nothing.');

        $action = \sprintf('/admin/cms/menus/%d/reattach', $detachedId);
        $token = $this->tokenOfTheForm($confirm, $action);

        self::assertSame(403, $this->send('POST', $action));
        self::assertInstanceOf(CmsMenu::class, CmsMenuQuery::findForLocale(self::CODE, $this->other), 'Refused without the token.');

        self::assertSame(302, $this->send('POST', $action, ['_token' => $token]));
        CmsMenuTableMap::clearInstancePool();
        self::assertNull(CmsMenuQuery::findForLocale(self::CODE, $this->other));
        self::assertSame(2, $this->entries($shared), 'The shared menu is untouched.');
    }

    public function testAnEntryOfAnotherRowOfTheCodeIsNotFound(): void
    {
        $shared = $this->sharedMenu(['Accueil']);
        $detached = $this->detach($shared);
        $sharedItem = CmsMenuItemQuery::create()->filterByMenuId($shared->getId())->findOne();
        self::assertInstanceOf(CmsMenuItem::class, $sharedItem);
        $token = $this->tokenOfTheScripts($this->screen($this->url($detached, $this->otherId)));

        foreach ([
            ['POST', \sprintf('/admin/cms/menus/%d/entries/%d/delete', $detached->getId(), $sharedItem->getId())],
            ['POST', \sprintf('/admin/cms/menus/%d/entries/%d/move/down', $detached->getId(), $sharedItem->getId())],
            ['POST', \sprintf('/admin/cms/menus/%d/entries/%d/place', $detached->getId(), $sharedItem->getId())],
        ] as [$method, $url]) {
            self::assertSame(404, $this->send($method, $url, ['_token' => $token, 'parent' => 0, 'position' => 1]), $url);
        }

        CmsMenuItemTableMap::clearInstancePool();
        self::assertNotNull(CmsMenuItemQuery::create()->findPk($sharedItem->getId()), 'The item of the shared menu stays.');
    }

    public function testTheEntriesOfADetachedMenuAreEditedApartFromTheSharedOne(): void
    {
        $shared = $this->sharedMenu(['Accueil', 'Contact']);
        $detached = $this->detach($shared);
        $first = CmsMenuItemQuery::create()->filterByMenuId($detached->getId())->orderByPosition()->findOne();
        self::assertInstanceOf(CmsMenuItem::class, $first);

        $action = \sprintf('/admin/cms/menus/%d/entries/%d/delete', $detached->getId(), $first->getId());
        $token = $this->tokenOfTheForm($this->screen($this->url($detached, $this->otherId)), $action);

        self::assertSame(302, $this->send('POST', $action.'?edit_language_id='.$this->otherId, ['_token' => $token]));
        self::assertSame(1, $this->entries($detached));
        self::assertSame(2, $this->entries($shared));
    }

    public function testTheMenuOfALanguageIsNotDeletedFromTheList(): void
    {
        $shared = $this->sharedMenu(['Accueil']);
        $detached = $this->detach($shared);
        $token = $this->tokenOfTheScripts($this->screen($this->url($detached, $this->otherId)));

        self::assertSame(404, $this->send('POST', \sprintf('/admin/cms/menus/%d/delete', $detached->getId()), ['_token' => $token]));
        self::assertInstanceOf(CmsMenu::class, CmsMenuQuery::findForLocale(self::CODE, $this->other));
    }

    public function testRealigningAndGoingBackNeedTheRightToDeleteToo(): void
    {
        $shared = $this->sharedMenu(['Accueil', 'Contact']);
        $detached = $this->detach($shared);
        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([CmsResources::MENU => [AccessManager::VIEW, AccessManager::UPDATE]]));

        $screen = $this->screen($this->url($detached, $this->otherId));
        self::assertSame(0, $screen->filter('[data-testid="cms-menu-realign"], [data-testid="cms-menu-realign-shared"], [data-testid="cms-menu-reattach"]')->count());
        $token = $this->tokenOfTheScripts($screen);

        foreach (['realign?source=shared', 'reattach'] as $action) {
            $url = \sprintf('/admin/cms/menus/%d/%s', $detached->getId(), $action).(str_contains($action, '?') ? '&' : '?').'edit_language_id='.$this->otherId;
            self::assertSame(403, $this->send('GET', $url), 'GET '.$action);
            self::assertSame(403, $this->send('POST', $url, ['source' => 'shared', '_token' => $token]), 'POST '.$action);
        }

        self::assertSame(2, $this->entries($detached));
        self::assertInstanceOf(CmsMenu::class, CmsMenuQuery::findForLocale(self::CODE, $this->other));
    }

    public function testTheListCountsTheEntriesOfTheMenuTheLanguageReads(): void
    {
        $shared = $this->sharedMenu(['Accueil', 'Contact']);
        $detached = $this->detach($shared);
        $this->entry($detached, 'Seulement ici', 3);
        $cell = \sprintf('[data-testid="cms-menu-entries-%d"]', $shared->getId());

        self::assertSame('3', trim(preg_replace('/\D.*/s', '', trim($this->screen('/admin/cms/menus?edit_language_id='.$this->otherId)->filter($cell)->text())) ?? ''));
        self::assertSame('2', trim(preg_replace('/\D.*/s', '', trim($this->screen('/admin/cms/menus?edit_language_id='.$this->languageId())->filter($cell)->text())) ?? ''));
    }

    public function testDeletingASharedMenuWarnsAboutItsLanguagesAndDeletesThem(): void
    {
        $shared = $this->sharedMenu(['Accueil']);
        $plain = $this->screen('/admin/cms/menus')->filter(\sprintf('[data-testid="cms-menu-row-%d"] form[action*="/delete"]', $shared->getId()))->attr('onsubmit');
        $detached = $this->detach($shared);
        $detachedId = (int) $detached->getId();

        $form = $this->screen('/admin/cms/menus')->filter(\sprintf('[data-testid="cms-menu-row-%d"] form[action*="/delete"]', $shared->getId()));
        self::assertNotSame($plain, $form->attr('onsubmit'), 'The question changes when languages have a menu of their own.');
        self::assertMatchesRegularExpression('/languages|langues/', (string) $form->attr('onsubmit'));

        self::assertSame(302, $this->send('POST', (string) $form->attr('action'), ['_token' => (string) $form->filter('input[name="_token"]')->attr('value')]));
        CmsMenuTableMap::clearInstancePool();
        self::assertNull(CmsMenuQuery::findForLocale(self::CODE, $this->other));
        self::assertNull(CmsMenuQuery::create()->findPk($detachedId));
        self::assertSame(0, CmsMenuItemQuery::create()->filterByMenuId($detachedId)->count());
    }

    private function url(CmsMenu $menu, int $languageId): string
    {
        return \sprintf('/admin/cms/menus/%d?edit_language_id=%d', $menu->getId(), $languageId);
    }

    private function languageId(): int
    {
        return (int) LangQuery::create()->findOneByLocale($this->locale())?->getId();
    }

    private function entries(CmsMenu $menu): int
    {
        return $this->getService(CmsMenuRepository::class)->entryCount($menu);
    }

    private function forgetMenus(): void
    {
        CmsMenuItemTableMap::clearInstancePool();
        $this->getService(MenuCache::class)->invalidate();
    }

    private function detach(CmsMenu $shared): CmsMenu
    {
        $detached = $this->getService(CmsMenuWriter::class)->detach((string) $shared->getCode(), $this->other);
        $this->forgetMenus();

        return $detached;
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

        $this->forgetMenus();

        return $item;
    }
}
