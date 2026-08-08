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

namespace TheliaCMS\Install;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\LangQuery;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContent;
use TheliaCMS\Page\CmsUrlService;

/**
 * Creates the legal pages every French site owes its visitors, as drafts.
 *
 * They are seeded rather than left to the integrator because the usual failure
 * mode is shipping without them. They are deliberately *not* published: the
 * placeholder text is not a legal notice, and putting it online would be worse
 * than having no page at all.
 */
final readonly class LegalPagesSeeder
{
    public function __construct(
        private CmsUrlService $urls = new CmsUrlService(),
    ) {
    }

    public function seed(): void
    {
        // Default language first: when two locales share a title ("Cookies"),
        // whichever is seeded first takes the clean slug and the other gets a
        // suffix, so it should be the shop's main language that wins.
        $activeLocales = array_map(
            static fn ($lang): string => (string) $lang->getLocale(),
            iterator_to_array(
                LangQuery::create()
                    ->filterByActive(1)
                    ->orderByByDefault(Criteria::DESC)
                    ->orderByPosition()
                    ->find(),
                false
            )
        );

        $position = 0;

        foreach (LegalPageTemplates::PAGES as $translations) {
            // Only the locales this seeder actually has a translation for. A
            // Spanish page carrying the English title would claim a URL
            // (`legal-notice-2`) and advertise a language it is not written in;
            // no page at all is the honest state until someone translates it.
            $locales = array_values(array_intersect($activeLocales, array_keys($translations)));

            if ([] === $locales) {
                continue;
            }

            $page = new CmsPage();
            $page->setParent(0)
                ->setPosition(++$position)
                // Online, but with no published content: the page is a draft
                // until someone writes the real text and hits publish.
                ->setVisible(1)
                ->setLayout('default');

            foreach ($locales as $locale) {
                $page->setLocale($locale)->setTitle($translations[$locale]['title']);
            }

            $page->save();

            foreach ($locales as $locale) {
                $translation = $translations[$locale];

                (new CmsPageContent())
                    ->setPageId($page->getId())
                    ->setLocale($locale)
                    ->setDraftHtml(LegalPageTemplates::html($translation))
                    ->save();

                // No explicit slug: the URL is derived from the localized
                // title, exactly as it is on every later save. Forcing the
                // English code here would give `legal-notice-2` to every
                // locale after the first, and leave a pointless 301 behind as
                // soon as the URLs are regenerated from the titles.
                $this->urls->refresh($page, $locale);
            }
        }
    }
}
