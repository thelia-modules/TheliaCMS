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

        return $url;
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
        $segments = [$segment];
        $parentId = (int) $page->getParent();
        $guard = 0;

        // 0 is the root, mirroring `category.parent` / `folder.parent`.
        while ($parentId > 0 && ++$guard < 20) {
            $parent = CmsPageQuery::create()->findPk($parentId);

            if (!$parent instanceof CmsPage) {
                break;
            }

            $parent->setLocale($locale);
            array_unshift($segments, $this->slugSource->slugify((string) $parent->getTitle()));
            $parentId = (int) $parent->getParent();
        }

        return $this->slugSource->truncate(implode('/', array_filter($segments)));
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
