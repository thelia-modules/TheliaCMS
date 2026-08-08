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

namespace TheliaCMS\Page;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\RewritingUrlQuery;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\TheliaCMS;

/**
 * Builds the hierarchical URL of a page and keeps `rewriting_url` in sync.
 *
 * The core generator is not reusable here: it collapses every non-word
 * character — slashes included — so `parent/child` would come out as
 * `parent-child`.
 */
final class CmsUrlService
{
    /**
     * A rewritten URL is matched before the Symfony routes (the rewriting
     * router runs at priority 1024), so a page slugged `admin/x` would shadow
     * the back office. First segments are checked against this list, both as
     * they were typed and as they come out of the slugifier — `robots.txt`
     * survives neither on its own, and a list nothing can ever match is worse
     * than no list.
     *
     * @var list<string>
     */
    private const RESERVED_PREFIXES = [
        'admin', 'api', 'assets', 'cache', 'media', 'sitemap',
        'robots.txt', 'robots-txt',
        // The icon of the site is served here, and the theme links to it from
        // every page.
        'site-icon',
        // The search results page answers on these, and the rewriting router
        // runs first: a page slugged "recherche" would shadow it.
        'recherche', 'search',
        '_profiler', 'profiler', '_wdt', 'wdt', '_fragment', 'fragment', '_error', 'error',
    ];

    public function __construct(
        private readonly PageSlugSource $slugSource = new PageSlugSource(),
    ) {
    }

    /**
     * Points the page URL for this locale at `$requestedSlug` (or at a slug
     * derived from the title), prefixed by its ancestors. The previous URL is
     * kept as a 301 by the core rewriting trait.
     */
    public function refresh(CmsPage $page, string $locale, ?string $requestedSlug = null): string
    {
        $page->setLocale($locale);

        $segment = $this->normalizeSegment($requestedSlug ?? (string) $page->getTitle());

        if ('' === $segment) {
            $segment = 'page-'.$page->getId();
        }

        $url = $this->makeUnique($this->prefixWithAncestors($page, $locale, $segment), $page->getId(), $locale);

        $page->setRewrittenUrl($locale, $url);
        $this->remember($page, $locale, $url);

        return $url;
    }

    /**
     * Puts the address of the page back without renaming it: the slug it
     * already answers on is reused, and only the ancestor prefix is computed
     * again.
     *
     * This is what a page needs when something around it moved — a
     * reactivation of the module, a restore from the bin, a re-parenting —
     * rather than `refresh()` with no slug, which derives the segment from the
     * title and therefore undoes every address anybody edited.
     */
    public function rebuild(CmsPage $page, string $locale): string
    {
        return $this->refresh($page, $locale, $this->slugOf($page, $locale));
    }

    /**
     * Puts back the address of a page and of every page under it, in each locale
     * they already answer in.
     *
     * This is what a move in the tree needs. The address of a page is made of
     * the addresses of its ancestors, so re-addressing the page that moved is
     * only the first of the addresses that changed: without this, a child keeps
     * announcing a path its parent no longer has, at every depth, with no
     * redirection from the old one and none towards the new one.
     *
     * The parent of a page is not a per-language column, so a move applies to
     * every language the page answers in and not only to the one being edited.
     * Only locales that already have an address take part: giving one to a
     * language nobody wrote the page in would publish an address for a
     * translation that does not exist.
     *
     * Cost is one pass per page and per locale, parents first, each of them a
     * handful of queries. Moving a page that sits near the root of a large site
     * therefore rewrites every address below it, which is the point, and is
     * worth a transaction around the call.
     *
     * @return int the number of addresses rewritten
     */
    public function rebuildSubtree(CmsPage $page): int
    {
        $rewritten = 0;

        foreach ([$page, ...$this->descendantsOf($page)] as $node) {
            foreach ($this->addressedLocales($node) as $locale) {
                $this->rebuild($node, $locale);
                ++$rewritten;
            }
        }

        return $rewritten;
    }

    /**
     * The pages below this one, closest first.
     *
     * Read level by level rather than page by page: a descendant is addressed
     * from the address of its parent, so the parent has to have been re-addressed
     * before its children are.
     *
     * A page that has ended up under one of its own descendants is walked once
     * and not followed again. The back office cannot build such a tree, since the
     * parent choices of a page leave out its own descendants, but an import or a
     * hand-written row can, and following parents on trust never comes back.
     *
     * @return list<CmsPage>
     */
    private function descendantsOf(CmsPage $page): array
    {
        $descendants = [];
        $walked = [(int) $page->getId() => true];
        $level = [(int) $page->getId()];

        while ([] !== $level) {
            $children = CmsPageQuery::create()
                ->filterByParent($level)
                ->filterByDeletedAt(null, Criteria::ISNULL)
                ->orderById()
                ->find();

            $level = [];

            foreach ($children as $child) {
                $id = (int) $child->getId();

                if (isset($walked[$id])) {
                    continue;
                }

                $walked[$id] = true;
                $descendants[] = $child;
                $level[] = $id;
            }
        }

        return $descendants;
    }

    /**
     * The locales this page currently answers in.
     *
     * Read from the addresses themselves rather than from the languages it has
     * content in: a page can hold a draft in a language it has never been given
     * an address in, and a rebuild must put addresses back, never invent one.
     *
     * @return list<string>
     */
    private function addressedLocales(CmsPage $page): array
    {
        $locales = RewritingUrlQuery::create()
            ->filterByView($page->getRewrittenUrlViewName())
            ->filterByViewId((string) $page->getId())
            ->filterByRedirected(null, Criteria::ISNULL)
            ->select(['ViewLocale'])
            ->find()
            ->toArray();

        return array_values(array_unique(array_map(strval(...), $locales)));
    }

    /**
     * The segment of its own address the page carries in this locale, which is
     * what the edit screen shows and what a rebuild reuses.
     *
     * Falls back to reading the address for a page written before the segment
     * was stored, so a site that has not replayed the migration still edits and
     * rebuilds the addresses it has.
     */
    public function slugOf(CmsPage $page, string $locale): ?string
    {
        $stored = trim((string) $page->setLocale($locale)->getSlug());

        if ('' !== $stored) {
            return $stored;
        }

        return $this->lastSegment((string) $page->getRewrittenUrl($locale));
    }

    /**
     * Stores the segment the address ends on, alongside the address itself.
     *
     * The two are written together on purpose: `rewriting_url` is a core table
     * the module clears when it is deactivated, so it cannot be the only place
     * an edited address exists.
     */
    private function remember(CmsPage $page, string $locale, string $url): void
    {
        $slug = $this->lastSegment($url);

        if (null === $slug || $slug === $page->setLocale($locale)->getSlug()) {
            return;
        }

        $page->setLocale($locale)->setSlug($slug)->save();
    }

    private function lastSegment(string $url): ?string
    {
        $url = trim($url, '/');

        if ('' === $url) {
            return null;
        }

        $segments = explode('/', $url);

        return end($segments) ?: null;
    }

    /**
     * @throws \InvalidArgumentException when the slug would shadow a reserved path
     */
    public function normalizeSegment(string $raw): string
    {
        $segment = $this->slugSource->slugify($raw);

        foreach ([strtolower(trim($raw)), $segment] as $candidate) {
            if (\in_array($candidate, self::RESERVED_PREFIXES, true)) {
                throw new \InvalidArgumentException(\sprintf('"%s" is a reserved path and cannot be used as a page slug.', $candidate));
            }
        }

        return $segment;
    }

    private function prefixWithAncestors(CmsPage $page, string $locale, string $segment): string
    {
        $prefix = $this->ancestorPath((int) $page->getParent(), $locale);

        return $this->slugSource->truncate('' === $prefix ? $segment : $prefix.'/'.$segment);
    }

    /**
     * The path the closest ancestor already answers on.
     *
     * Slugifying its title instead would ignore a slug edited by hand: a parent
     * answering on `/groupe` would give its children `/le-groupe/...`, so a
     * child would not live under its own parent. An ancestor with no address
     * yet in this locale — every one of them, while the addresses of the site
     * are being rebuilt — contributes its stored slug, and only a page that has
     * neither is named after its title.
     */
    private function ancestorPath(int $parentId, string $locale): string
    {
        $segments = [];
        $guard = 0;

        // 0 is the root, mirroring `category.parent` / `folder.parent`.
        while ($parentId > 0 && ++$guard < 20) {
            $parent = CmsPageQuery::create()->findPk($parentId);

            if (!$parent instanceof CmsPage) {
                break;
            }

            $url = trim((string) $parent->getRewrittenUrl($locale), '/');

            if ('' !== $url) {
                // That address already carries its own ancestors.
                array_unshift($segments, $url);

                break;
            }

            $parent->setLocale($locale);
            $ownSegment = trim((string) $parent->getSlug());
            array_unshift($segments, $this->slugSource->slugify('' !== $ownSegment ? $ownSegment : (string) $parent->getTitle()));
            $parentId = (int) $parent->getParent();
        }

        return implode('/', array_filter($segments));
    }

    private function makeUnique(string $url, int $pageId, string $locale): string
    {
        $candidate = $url;
        $suffix = 1;

        while ($this->isTakenByAnotherPage($candidate, $pageId, $locale)) {
            ++$suffix;
            $candidate = $this->slugSource->truncate($url).'-'.$suffix;
        }

        return $candidate;
    }

    private function isTakenByAnotherPage(string $url, int $pageId, string $locale): bool
    {
        $existing = RewritingUrlQuery::create()->findOneByUrl($url);

        if (null === $existing) {
            return false;
        }

        return TheliaCMS::PAGE_VIEW !== $existing->getView()
            || (string) $pageId !== (string) $existing->getViewId()
            || $locale !== $existing->getViewLocale();
    }
}
