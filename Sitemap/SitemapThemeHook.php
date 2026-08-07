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

namespace TheliaCMS\Sitemap;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Thelia\Model\LangQuery;
use Thelia\Tools\URL;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageI18nQuery;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\TheliaCMS;

/**
 * Adds the CMS pages to the sitemap of the theme.
 *
 * A theme opens its sitemap by calling `theme_hook('sitemap.urls')`, and any
 * module answers there. That is what a section registry would have been, minus
 * the registry: the theme decides where the entries go and the module decides
 * what they are.
 *
 * `lastmod` is the publication date, never the update date of the row. A row is
 * touched by things a reader never sees, and a sitemap that claims every page
 * changed last night is a sitemap a crawler learns to distrust.
 */
final readonly class SitemapThemeHook implements ThemeHookInterface
{
    public function supports(string $hookName): bool
    {
        return 'sitemap.urls' === $hookName;
    }

    public function render(string $hookName, array $parameters): string
    {
        $context = (string) ($parameters['context'] ?? '');

        // The theme splits its sitemap into contexts so a big catalogue can be
        // generated in pieces. Pages belong to the content one.
        if ('' !== $context && 'content' !== $context) {
            return '';
        }

        $locales = $this->locales((string) ($parameters['lang'] ?? ''));

        if ([] === $locales) {
            return '';
        }

        $out = [];

        foreach ($this->publishedPages() as $pageId => $publishedLocales) {
            foreach ($locales as $locale) {
                if (!isset($publishedLocales[$locale])) {
                    continue;
                }

                $url = $this->urlOf($pageId, $locale);

                if (null === $url) {
                    continue;
                }

                $out[] = SitemapEntry::toXml($url, $publishedLocales[$locale], $this->alternates($pageId, array_keys($publishedLocales)));
            }
        }

        return implode("\n", $out);
    }

    /**
     * The page in every language it is published in, itself included.
     *
     * @param list<string> $publishedIn
     *
     * @return array<string, string>
     */
    private function alternates(int $pageId, array $publishedIn): array
    {
        $alternates = [];

        foreach ($publishedIn as $locale) {
            $url = $this->urlOf($pageId, $locale);

            if (null !== $url) {
                $alternates[$locale] = $url;
            }
        }

        return $alternates;
    }

    /**
     * Pages a visitor may reach, and when each language of them went live.
     *
     * @return array<int, array<string, \DateTimeInterface>>
     */
    private function publishedPages(): array
    {
        $now = new \DateTimeImmutable();

        $pages = CmsPageQuery::create()
            ->filterByDeletedAt(null, Criteria::ISNULL)
            ->filterByVisible(1)
            ->find();

        $noindex = [];

        foreach (CmsPageI18nQuery::create()->filterByNoindex(1)->find() as $translation) {
            $noindex[(int) $translation->getId()][(string) $translation->getLocale()] = true;
        }

        $published = [];

        foreach ($pages as $page) {
            $publishAt = $page->getPublishAt();
            $unpublishAt = $page->getUnpublishAt();

            if ((null !== $publishAt && $publishAt > $now) || (null !== $unpublishAt && $unpublishAt <= $now)) {
                continue;
            }

            $pageId = (int) $page->getId();

            $contents = CmsPageContentQuery::create()
                ->filterByPageId($pageId)
                ->filterByPublishedAt(null, Criteria::ISNOTNULL)
                ->find();

            foreach ($contents as $content) {
                $locale = (string) $content->getLocale();

                if (isset($noindex[$pageId][$locale])) {
                    continue;
                }

                $published[$pageId][$locale] = $content->getPublishedAt();
            }
        }

        return $published;
    }

    /**
     * @return list<string>
     */
    private function locales(string $requested): array
    {
        $active = [];

        foreach (LangQuery::create()->filterByActive(1)->find() as $lang) {
            $active[] = (string) $lang->getLocale();
        }

        if ('' === $requested) {
            return $active;
        }

        // The theme passes either a locale or a two-letter code, depending on
        // how the sitemap was asked for.
        return array_values(array_filter(
            $active,
            static fn (string $locale): bool => $locale === $requested || str_starts_with($locale, $requested.'_'),
        ));
    }

    private function urlOf(int $pageId, string $locale): ?string
    {
        $urls = URL::getInstance();

        if (null === $urls) {
            return null;
        }

        try {
            return $urls->retrieve(TheliaCMS::PAGE_VIEW, $pageId, $locale)->toString();
        } catch (\Throwable) {
            return null;
        }
    }
}
