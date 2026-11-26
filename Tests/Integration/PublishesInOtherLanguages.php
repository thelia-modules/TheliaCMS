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

namespace TheliaCMS\Tests\Integration;

use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use TheliaCMS\Content\Document;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Page\Admin\PageDraft;

/**
 * Cases about what is advertised per language: a page published in several
 * languages, a language switched off or hidden, a language marked noindex.
 *
 * `Lang::getDefaultLanguage()` and `Lang::getActiveLangs()` keep a static value
 * that an update of the `lang` table does not drop: the case that changes a
 * language calls `forgetLanguageCaches()` in `setUp()` and `tearDown()`.
 */
trait PublishesInOtherLanguages
{
    protected function forgetLanguageCaches(): void
    {
        Lang::resetDefaultLanguageCache();
        Lang::resetActiveLangsCache();
    }

    protected function publishIn(CmsPage $page, string $locale, string $title): void
    {
        $writer = $this->writer();
        $writer->saveDraft($page, $locale, new PageDraft(title: $title));
        $writer->saveContent($page, $locale, Document::richText(\sprintf('<h2>%s</h2>', $title)));
        $writer->publish($page, $locale);
    }

    protected function markNoindexIn(CmsPage $page, string $locale): void
    {
        $page->setLocale($locale)->setNoindex(1)->save();
    }

    protected function hideLanguage(string $locale): void
    {
        LangQuery::create()->filterByLocale($locale)->update(['Visible' => 0]);
        $this->forgetLanguageCaches();
    }

    /**
     * @return array{0: string, 1: string} two active, visible languages other than the one the module answers in
     */
    protected function twoOtherLocales(): array
    {
        $others = [];

        foreach (LangQuery::create()->filterByActive(true)->filterByVisible(true)->orderById()->find() as $lang) {
            if ($lang->getLocale() !== $this->locale()) {
                $others[] = (string) $lang->getLocale();
            }
        }

        if (2 > \count($others)) {
            self::markTestSkipped('this host has fewer than three active and visible languages');
        }

        return [$others[0], $others[1]];
    }
}
