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
    /**
     * @var array<string, array<string, array{title: string, intro: string, sections: list<string>}>>
     */
    private const PAGES = [
        'legal-notice' => [
            'en_US' => [
                'title' => 'Legal notice',
                'intro' => 'Replace this placeholder with your own legal notice before publishing the page.',
                'sections' => ['Publisher', 'Hosting provider', 'Publication director', 'Contact'],
            ],
            'fr_FR' => [
                'title' => 'Mentions légales',
                'intro' => 'Remplacez ce texte d’exemple par vos mentions légales avant de publier la page.',
                'sections' => ['Éditeur du site', 'Hébergeur', 'Directeur de la publication', 'Contact'],
            ],
        ],
        'privacy-policy' => [
            'en_US' => [
                'title' => 'Privacy policy',
                'intro' => 'Replace this placeholder with your own privacy policy before publishing the page.',
                'sections' => ['Data we collect', 'Why we collect it', 'How long we keep it', 'Your rights', 'Contact our data protection officer'],
            ],
            'fr_FR' => [
                'title' => 'Politique de confidentialité',
                'intro' => 'Remplacez ce texte d’exemple par votre politique de confidentialité avant de publier la page.',
                'sections' => ['Données collectées', 'Finalités du traitement', 'Durée de conservation', 'Vos droits', 'Contacter le délégué à la protection des données'],
            ],
        ],
        'cookies' => [
            'en_US' => [
                'title' => 'Cookies',
                'intro' => 'List here every cookie and every storage your site sets, its purpose and its lifetime.',
                'sections' => ['Strictly necessary', 'Audience measurement', 'Advertising', 'Changing your choices'],
            ],
            'fr_FR' => [
                'title' => 'Cookies',
                'intro' => 'Recensez ici chaque cookie et chaque stockage posé par votre site, sa finalité et sa durée.',
                'sections' => ['Strictement nécessaires', 'Mesure d’audience', 'Publicité', 'Modifier vos choix'],
            ],
        ],
        'accessibility' => [
            'en_US' => [
                'title' => 'Accessibility statement',
                'intro' => 'Never claim a conformance level you have not had audited. Until an audit is done, state that the site has not been assessed.',
                'sections' => ['Conformance status', 'Test results', 'Content not accessible', 'Feedback and contact', 'Enforcement procedure'],
            ],
            'fr_FR' => [
                'title' => 'Déclaration d’accessibilité',
                'intro' => 'N’annoncez jamais un niveau de conformité qui n’a pas été audité. Tant qu’aucun audit n’a eu lieu, indiquez que le site n’a pas été évalué.',
                'sections' => ['État de conformité', 'Résultats des tests', 'Contenus non accessibles', 'Retour d’information et contact', 'Voie de recours'],
            ],
        ],
    ];

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

        foreach (self::PAGES as $translations) {
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
                    ->setDraftHtml($this->placeholder($translation))
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

    /**
     * @param array{title: string, intro: string, sections: list<string>} $translation
     */
    private function placeholder(array $translation): string
    {
        $html = '<h1>'.htmlspecialchars($translation['title'], \ENT_QUOTES).'</h1>';
        $html .= '<p>'.htmlspecialchars($translation['intro'], \ENT_QUOTES).'</p>';

        foreach ($translation['sections'] as $section) {
            $html .= '<h2>'.htmlspecialchars($section, \ENT_QUOTES).'</h2><p></p>';
        }

        return $html;
    }
}
