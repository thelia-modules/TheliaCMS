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

use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\RewritingUrlQuery;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Page\Admin\BuilderContent;
use TheliaCMS\Page\Admin\PageDraft;
use TheliaCMS\Page\CmsUrlService;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * The address of a page inside the tree.
 *
 * What a visitor and a search engine expect is that a child sits under the path
 * of its parent. That holds only if the prefix is read from the address the
 * parent answers on, and not rebuilt from its title, which is a different
 * string as soon as anyone edits a slug by hand.
 */
final class PageAddressTest extends CmsIntegrationTestCase
{
    public function testAChildSitsUnderTheAddressItsParentAnswersOn(): void
    {
        $parent = $this->pageWithSlug('Le Groupe', 'groupe');

        $child = $this->createPage('Agence web Lyon', parent: (int) $parent->getId());

        self::assertSame('groupe', $parent->getRewrittenUrl($this->locale()));
        self::assertSame('groupe/agence-web-lyon', $child->getRewrittenUrl($this->locale()));
    }

    public function testEveryLevelOfTheTreeFollowsTheLevelAboveIt(): void
    {
        $top = $this->pageWithSlug('Nos métiers', 'metiers');
        $middle = $this->pageWithSlug('Développement web sur mesure', 'developpement-sur-mesure-2025', (int) $top->getId());

        $leaf = $this->createPage('CRM', parent: (int) $middle->getId());

        self::assertSame('metiers/developpement-sur-mesure-2025', $middle->getRewrittenUrl($this->locale()));
        self::assertSame('metiers/developpement-sur-mesure-2025/crm', $leaf->getRewrittenUrl($this->locale()));
    }

    /**
     * The everyday case, where nobody edited a slug: the address is unchanged.
     */
    public function testAnUntouchedSlugStillComposesTheAddressFromTheTitles(): void
    {
        $parent = $this->createPage('Nos engagements');
        $child = $this->createPage('Notre charte', parent: (int) $parent->getId());

        self::assertSame('nos-engagements', $parent->getRewrittenUrl($this->locale()));
        self::assertSame('nos-engagements/notre-charte', $child->getRewrittenUrl($this->locale()));
    }

    /**
     * The edit screen shows the segment the page owns, not its whole path.
     *
     * Whatever it shows comes back on the next save, so if it shows the path,
     * that path is slugified into a single segment and prefixed by the
     * ancestors again: opening a child page and pressing save, changing
     * nothing, moves it to `parent/parent-child` and leaves a 301 behind.
     */
    public function testSavingAPageAgainWithTheAddressTheScreenShowsLeavesItWhereItIs(): void
    {
        $parent = $this->createPage('Nos services');
        $child = $this->createPage('Conseil et accompagnement', parent: (int) $parent->getId());

        self::assertSame('nos-services/conseil-et-accompagnement', $child->getRewrittenUrl($this->locale()));

        $shown = $this->getService(CmsUrlService::class)->slugOf($child, $this->locale());

        $this->writer()->saveDraft($child, $this->locale(), new PageDraft(title: 'Conseil et accompagnement', slug: $shown));

        self::assertSame(
            'nos-services/conseil-et-accompagnement',
            $child->getRewrittenUrl($this->locale()),
            'Saving a page again with the address its own screen displays does not move it.',
        );
    }

    /**
     * Moving a page takes its whole subtree with it.
     *
     * The address of a descendant is made of the address of its ancestors, so a
     * page that changes parent changes the address of everything under it. Left
     * alone, a grandchild keeps announcing a path that no longer exists in the
     * tree, at any depth.
     */
    public function testMovingAPageMovesTheAddressesOfItsDescendants(): void
    {
        $formerParent = $this->createPage('Ancienne rubrique');
        $newParent = $this->createPage('Nouvelle rubrique');
        $branch = $this->createPage('Notre offre', parent: (int) $formerParent->getId());
        $leaf = $this->createPage('Conseil', parent: (int) $branch->getId());
        $deepest = $this->createPage('Audit', parent: (int) $leaf->getId());

        self::assertSame('ancienne-rubrique/notre-offre/conseil/audit', $deepest->getRewrittenUrl($this->locale()));

        $this->moveUnder($branch, (int) $newParent->getId());

        self::assertSame('nouvelle-rubrique/notre-offre', $branch->getRewrittenUrl($this->locale()));
        self::assertSame('nouvelle-rubrique/notre-offre/conseil', $leaf->getRewrittenUrl($this->locale()));
        self::assertSame(
            'nouvelle-rubrique/notre-offre/conseil/audit',
            $deepest->getRewrittenUrl($this->locale()),
            'A page three levels below the one that moved follows it too.',
        );
    }

    /**
     * The former address of every descendant answers a 301 on the new one.
     *
     * This is what the core rewriting trait does when an address is replaced: it
     * points the old row at the new one, and the rewriting router redirects. It
     * only happens for the pages the module actually re-addresses, which is the
     * whole point of the test.
     */
    public function testTheFormerAddressOfAMovedDescendantRedirects(): void
    {
        $formerParent = $this->createPage('Rubrique de départ');
        $newParent = $this->createPage('Rubrique d’arrivée');
        $branch = $this->createPage('Notre agence', parent: (int) $formerParent->getId());
        $leaf = $this->createPage('Notre équipe', parent: (int) $branch->getId());

        $formerLeafAddress = (string) $leaf->getRewrittenUrl($this->locale());

        $this->moveUnder($branch, (int) $newParent->getId());

        $newLeafAddress = (string) $leaf->getRewrittenUrl($this->locale());

        self::assertNotSame($formerLeafAddress, $newLeafAddress);

        $former = RewritingUrlQuery::create()->findOneByUrl($formerLeafAddress);
        $target = RewritingUrlQuery::create()->findOneByUrl($newLeafAddress);

        self::assertNotNull($former, 'The address a page used to answer on is kept.');
        self::assertNotNull($target);
        self::assertSame(
            (int) $target->getId(),
            (int) $former->getRedirected(),
            'The former address of a moved descendant points at its new one, which is what makes the router answer 301.',
        );
    }

    /**
     * A slug somebody typed survives the move of an ancestor.
     *
     * Re-addressing a descendant recomputes the path of its ancestors and
     * nothing else. Deriving the segment from the title again would rename every
     * address of the subtree, which is the failure the stored slug exists to
     * prevent.
     */
    public function testMovingAPageKeepsTheSlugsItsDescendantsWereGiven(): void
    {
        $formerParent = $this->createPage('Section A');
        $newParent = $this->createPage('Section B');
        $branch = $this->createPage('Nos métiers', parent: (int) $formerParent->getId());
        $leaf = $this->pageWithSlug('Développement web sur mesure', 'dev-2025', (int) $branch->getId());

        $this->moveUnder($branch, (int) $newParent->getId());

        self::assertSame('section-b/nos-metiers/dev-2025', $leaf->getRewrittenUrl($this->locale()));
    }

    /**
     * A page whose parent is one of its own descendants cannot be walked
     * forever.
     *
     * The back office cannot build such a tree: the parent choices leave out the
     * descendants of the page being edited. An import, a restore of an old dump
     * or a hand-written row can, and a walk that follows parents blindly never
     * comes back.
     */
    public function testASubtreeThatContainsItselfStillTerminates(): void
    {
        $top = $this->createPage('Boucle haute');
        $child = $this->createPage('Boucle basse', parent: (int) $top->getId());

        // The tree now holds a cycle: each of the two pages is under the other.
        $top->setParent((int) $child->getId())->save();

        $rewritten = $this->getService(CmsUrlService::class)->rebuildSubtree($child);

        self::assertSame(2, $rewritten, 'Each page of the cycle is addressed once, and the walk stops there.');
    }

    /**
     * A page rebuilt from nothing gets its whole path back, however deep it sits.
     *
     * This is the state a reactivation of the module leaves behind: the addresses
     * it owned are gone, so the path of a page has to be climbed ancestor by
     * ancestor from the stored slugs. The climb used to stop after a fixed number
     * of them, which silently cut the top off the address of a page nested
     * deeper, and put it online somewhere else.
     */
    public function testADeeplyNestedPageRebuiltFromNothingKeepsItsWholePath(): void
    {
        $depth = 24;
        $parent = 0;
        $expected = [];
        $deepest = null;

        for ($level = 1; $level <= $depth; ++$level) {
            $deepest = $this->createPage('Niveau '.$level, parent: $parent);
            $parent = (int) $deepest->getId();
            $expected[] = 'niveau-'.$level;
        }

        self::assertNotNull($deepest);

        // What preDeactivation() does: every address the module owned is dropped,
        // and only the stored slugs are left to rebuild them from.
        RewritingUrlQuery::create()->filterByView($deepest->getRewrittenUrlViewName())->delete();

        $rebuilt = $this->getService(CmsUrlService::class)->rebuild($deepest, $this->locale());

        self::assertSame(implode('/', $expected), $rebuilt);
    }

    /**
     * A move applies to every language, not to the one being edited.
     *
     * The parent of a page is a single column shared by its translations, so
     * saving the French settings of a page moves its English address too, and the
     * addresses of its descendants in both.
     */
    public function testAMoveFollowsTheAddressesOfEveryLanguageThePagesAnswerIn(): void
    {
        $second = $this->secondLocale();

        $formerParent = $this->bilingualPage('Former area', 'Ancien domaine', $second);
        $newParent = $this->bilingualPage('New area', 'Nouveau domaine', $second);
        $branch = $this->bilingualPage('Our trades', 'Nos métiers', $second, (int) $formerParent->getId());
        $leaf = $this->bilingualPage('Advice', 'Conseil', $second, (int) $branch->getId());

        self::assertSame('ancien-domaine/nos-metiers/conseil', $leaf->getRewrittenUrl($second));

        $this->moveUnder($branch, (int) $newParent->getId());

        self::assertSame('new-area/our-trades', $branch->getRewrittenUrl($this->locale()));
        self::assertSame(
            'nouveau-domaine/nos-metiers',
            $branch->getRewrittenUrl($second),
            'The page that moved is re-addressed in the languages it answers in, not only in the one being edited.',
        );
        self::assertSame('new-area/our-trades/advice', $leaf->getRewrittenUrl($this->locale()));
        self::assertSame('nouveau-domaine/nos-metiers/conseil', $leaf->getRewrittenUrl($second));
    }

    /**
     * A rebuild puts addresses back and never invents one.
     *
     * A page can hold a draft in a language it was never given an address in: a
     * translation started and not saved from the settings screen. Addressing it
     * because an ancestor moved would put a page online under a language nobody
     * wrote it in.
     */
    public function testAMoveDoesNotGiveAnAddressToALanguageThePageHasNone(): void
    {
        $second = $this->secondLocale();

        $formerParent = $this->createPage('Quiet area');
        $newParent = $this->createPage('Louder area');
        $branch = $this->createPage('Trades', parent: (int) $formerParent->getId());
        $leaf = $this->createPage('Support', parent: (int) $branch->getId());

        // Content in the second language, and no address in it: what an import,
        // or a translation written in the builder alone, leaves behind.
        $this->writer()->saveContent($leaf, $second, new BuilderContent(
            projectData: '{"pages":[]}',
            html: '<h1>Assistance</h1>',
            css: '',
        ));

        self::assertNull($leaf->getRewrittenUrl($second), 'Saving content in a language does not address the page in it.');

        $this->moveUnder($branch, (int) $newParent->getId());

        self::assertSame('louder-area/trades/support', $leaf->getRewrittenUrl($this->locale()));
        self::assertNull(
            $leaf->getRewrittenUrl($second),
            'A rebuild puts addresses back and never gives one to a language the page did not answer in.',
        );
    }

    /**
     * Another active language of the shop, whichever it is.
     */
    private function secondLocale(): string
    {
        $locales = array_map(
            static fn (Lang $lang): string => (string) $lang->getLocale(),
            iterator_to_array(LangQuery::create()->filterByActive(1)->orderByPosition()->find(), false),
        );

        $others = array_values(array_diff($locales, [$this->locale()]));

        if ([] === $others) {
            self::markTestSkipped('The shop has a single active language, so nothing here can be measured.');
        }

        return $others[0];
    }

    private function bilingualPage(string $title, string $otherTitle, string $otherLocale, int $parent = 0): CmsPage
    {
        $page = $this->createPage($title, parent: $parent);
        $this->writer()->saveDraft($page, $otherLocale, new PageDraft(title: $otherTitle));

        return $page;
    }

    private function pageWithSlug(string $title, string $slug, int $parent = 0): CmsPage
    {
        $page = $this->createPage($title, parent: $parent);
        $this->writer()->saveDraft($page, $this->locale(), new PageDraft(title: $title, slug: $slug));

        return $page;
    }

    /**
     * Re-parents a page the way the edit screen does: the parent is set on the
     * object, then the settings form is saved with the slug the screen shows.
     */
    private function moveUnder(CmsPage $page, int $parent): void
    {
        $urls = $this->getService(CmsUrlService::class);
        $locale = $this->locale();

        $page->setParent($parent);

        $this->writer()->saveDraft($page, $locale, new PageDraft(
            title: (string) $page->setLocale($locale)->getTitle(),
            slug: $urls->slugOf($page, $locale),
        ));
    }
}
