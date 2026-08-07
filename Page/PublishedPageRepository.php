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
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageQuery;

/**
 * The single front-office read path for pages. Draft columns are never selected
 * here: a page the visitor sees is always the published snapshot.
 */
final readonly class PublishedPageRepository
{
    public function find(int $pageId, string $locale, ?\DateTimeInterface $now = null): ?PublishedPage
    {
        $page = CmsPageQuery::create()->findPk($pageId);

        if (!$page instanceof CmsPage || !$this->isLive($page, $now ?? new \DateTimeImmutable())) {
            return null;
        }

        $content = CmsPageContentQuery::create()
            ->filterByPageId($pageId)
            ->filterByLocale($locale)
            ->findOne();

        if (null === $content || null === $content->getPublishedAt() || null === $content->getPublishedHtml()) {
            return null;
        }

        $page->setLocale($locale);

        return new PublishedPage(
            id: $pageId,
            locale: $locale,
            title: (string) $page->getTitle(),
            layout: PageLayout::fromStorage($page->getLayout()),
            html: (string) $content->getPublishedHtml(),
            css: (string) $content->getPublishedCss(),
            metaTitle: $this->nullIfBlank($page->getMetaTitle()),
            metaDescription: $this->nullIfBlank($page->getMetaDescription()),
            noindex: 1 === $page->getNoindex(),
            nofollow: 1 === $page->getNofollow(),
            publishedAt: $content->getPublishedAt(),
        );
    }

    /**
     * The draft of a page, shaped exactly like a published one so the same
     * template renders it.
     *
     * Only reachable through a signed preview link, and always marked
     * `noindex`: a draft that a search engine picks up is a page published by
     * accident.
     */
    public function draft(int $pageId, string $locale): ?PublishedPage
    {
        $page = CmsPageQuery::create()->findPk($pageId);

        if (!$page instanceof CmsPage || null !== $page->getDeletedAt()) {
            return null;
        }

        $content = CmsPageContentQuery::create()
            ->filterByPageId($pageId)
            ->filterByLocale($locale)
            ->findOne();

        if (null === $content || null === $content->getDraftHtml()) {
            return null;
        }

        $page->setLocale($locale);

        return new PublishedPage(
            id: $pageId,
            locale: $locale,
            title: (string) $page->getTitle(),
            layout: PageLayout::fromStorage($page->getLayout()),
            html: (string) $content->getDraftHtml(),
            css: (string) $content->getDraftCss(),
            metaTitle: $this->nullIfBlank($page->getMetaTitle()),
            metaDescription: $this->nullIfBlank($page->getMetaDescription()),
            noindex: true,
            nofollow: true,
        );
    }

    /**
     * Whether a visitor opening this page in this language gets it, rather than
     * a 404.
     *
     * Same conditions as find(), without reading the published HTML: callers
     * ask this about pages they are not going to render, and that column holds
     * the whole page.
     */
    public function isReachable(int $pageId, string $locale, ?\DateTimeInterface $now = null): bool
    {
        $page = CmsPageQuery::create()->findPk($pageId);

        if (!$page instanceof CmsPage || !$this->isLive($page, $now ?? new \DateTimeImmutable())) {
            return false;
        }

        return 0 < CmsPageContentQuery::create()
            ->filterByPageId($pageId)
            ->filterByLocale($locale)
            ->filterByPublishedAt(null, Criteria::ISNOTNULL)
            ->filterByPublishedHtml(null, Criteria::ISNOTNULL)
            ->count();
    }

    /**
     * Locales the page is actually published in — feeds hreflang and the
     * language switcher.
     *
     * @return list<string>
     */
    public function publishedLocales(int $pageId): array
    {
        $contents = CmsPageContentQuery::create()
            ->filterByPageId($pageId)
            ->filterByPublishedAt(null, Criteria::ISNOTNULL)
            ->select(['Locale'])
            ->find()
            ->toArray();

        return array_values(array_map(strval(...), $contents));
    }

    /**
     * Visible, not in the bin, and inside its publication window.
     */
    private function isLive(CmsPage $page, \DateTimeInterface $now): bool
    {
        if (null !== $page->getDeletedAt() || 1 !== $page->getVisible()) {
            return false;
        }

        $publishAt = $page->getPublishAt();
        if (null !== $publishAt && $publishAt > $now) {
            return false;
        }

        $unpublishAt = $page->getUnpublishAt();

        return null === $unpublishAt || $unpublishAt > $now;
    }

    private function nullIfBlank(?string $value): ?string
    {
        return '' === trim((string) $value) ? null : $value;
    }
}
