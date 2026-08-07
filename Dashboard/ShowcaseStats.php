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

namespace TheliaCMS\Dashboard;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\LangQuery;
use TheliaCMS\Model\CmsFormSubmissionQuery;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Model\CmsScriptQuery;

/**
 * What somebody running a showcase site opens the back office to find out.
 *
 * The dashboard of the core counts orders and turnover, which on a site with no
 * shop is a screen of zeros. These are the numbers that mean something instead:
 * how many people wrote in, what was changed last, and whether the site is
 * finished in every language it claims to speak.
 */
final readonly class ShowcaseStats
{
    private const int RECENT_DAYS = 30;
    private const int RECENT_PAGES = 5;

    /**
     * @return array{
     *     submissions_recent: int,
     *     submissions_total: int,
     *     recent_days: int,
     *     pages_published: int,
     *     pages_draft: int,
     *     recent_pages: list<array{id: int, title: string, updated_at: ?\DateTimeInterface}>,
     *     completeness: list<array{locale: string, title: string, published: int, total: int}>,
     *     measurement: int,
     * }
     */
    public function collect(string $locale): array
    {
        $since = (new \DateTimeImmutable())->modify('-'.self::RECENT_DAYS.' days');

        return [
            'submissions_recent' => CmsFormSubmissionQuery::create()
                ->filterByCreatedAt($since, Criteria::GREATER_EQUAL)
                ->count(),
            'submissions_total' => CmsFormSubmissionQuery::create()->count(),
            'recent_days' => self::RECENT_DAYS,
            'pages_published' => $this->publishedPageCount(),
            'pages_draft' => $this->livePages()->count() - $this->publishedPageCount(),
            'recent_pages' => $this->recentPages($locale),
            'completeness' => $this->completeness(),
            // Whether anything is actually measuring. A site nobody measures is
            // a site whose owner finds out about a problem from a phone call.
            'measurement' => CmsScriptQuery::create()->filterByActive(1)->count(),
        ];
    }

    /**
     * @return list<array{id: int, title: string, updated_at: ?\DateTimeInterface}>
     */
    private function recentPages(string $locale): array
    {
        $pages = $this->livePages()
            ->joinWithI18n($locale, Criteria::LEFT_JOIN)
            ->orderByUpdatedAt(Criteria::DESC)
            ->limit(self::RECENT_PAGES)
            ->find();

        $recent = [];

        foreach ($pages as $page) {
            $page->setLocale($locale);

            $recent[] = [
                'id' => (int) $page->getId(),
                'title' => (string) ($page->getTitle() ?: '#'.$page->getId()),
                'updated_at' => $page->getUpdatedAt(),
            ];
        }

        return $recent;
    }

    /**
     * How much of the site exists in each language.
     *
     * A page written in French and never translated is invisible to half the
     * visitors of a bilingual site, and nothing else in the back office says so.
     *
     * @return list<array{locale: string, title: string, published: int, total: int}>
     */
    private function completeness(): array
    {
        $total = $this->livePages()->count();
        $rows = [];

        foreach (LangQuery::create()->filterByActive(1)->orderByPosition()->find() as $lang) {
            $locale = (string) $lang->getLocale();

            $rows[] = [
                'locale' => $locale,
                'title' => (string) $lang->getTitle(),
                'published' => CmsPageContentQuery::create()
                    ->filterByLocale($locale)
                    ->filterByPublishedAt(null, Criteria::ISNOTNULL)
                    ->useCmsPageQuery()
                        ->filterByDeletedAt(null, Criteria::ISNULL)
                    ->endUse()
                    ->count(),
                'total' => $total,
            ];
        }

        return $rows;
    }

    private function publishedPageCount(): int
    {
        return CmsPageContentQuery::create()
            ->filterByPublishedAt(null, Criteria::ISNOTNULL)
            ->useCmsPageQuery()
                ->filterByDeletedAt(null, Criteria::ISNULL)
            ->endUse()
            ->groupByPageId()
            ->count();
    }

    /**
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria<CmsPage>
     */
    private function livePages(): \Propel\Runtime\ActiveQuery\ModelCriteria
    {
        return CmsPageQuery::create()->filterByDeletedAt(null, Criteria::ISNULL);
    }
}
