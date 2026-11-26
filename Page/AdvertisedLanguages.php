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

use Thelia\Model\LangQuery;
use TheliaCMS\Model\CmsPageI18nQuery;

/**
 * Which languages of a page may be advertised to a crawler: the hreflang tags
 * and the sitemap, entries and alternates alike.
 *
 * One rule: a language where the page is noindex or not visible is left out of
 * both. The page itself is not touched — it exists, and a visitor may go there,
 * which is why the language switcher (`AlternateUrls::all()`) does not ask.
 *
 * Whether the page is live and published in the language is another question,
 * `PublishedPageRepository::isReachable()` for a page asked about, the same
 * statement for the sitemap: this class only narrows what that lets through.
 *
 * The sitemap reads thousands of pages in one statement and cannot ask this
 * class page by page: it takes the same two conditions as SQL
 * (`LANGUAGE_CONDITION`, on the aliases `l` for `lang` and `i` for
 * `cms_page_i18n`), and a change of the rule is made here, in both.
 */
final readonly class AdvertisedLanguages
{
    /** The rule, as the sitemap's statement reads it. */
    public const string LANGUAGE_CONDITION = 'l.active = 1 AND l.visible = 1 AND COALESCE(i.noindex, 0) = 0';

    public function isAdvertised(int $pageId, string $locale): bool
    {
        return $this->languageIsShown($locale) && !$this->isNoindex($pageId, $locale);
    }

    private function languageIsShown(string $locale): bool
    {
        return 0 < LangQuery::create()
            ->filterByLocale($locale)
            ->filterByActive(true)
            ->filterByVisible(true)
            ->count();
    }

    private function isNoindex(int $pageId, string $locale): bool
    {
        $i18n = CmsPageI18nQuery::create()->filterById($pageId)->filterByLocale($locale)->findOne();

        return null !== $i18n && 0 !== (int) $i18n->getNoindex();
    }
}
