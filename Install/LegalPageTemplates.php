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

/**
 * The sample text the legal pages are seeded with.
 *
 * Kept apart from the seeder because two pieces of code need the same strings:
 * the seeder, which writes them, and the check that refuses to put them online.
 * A copy on either side would drift, and the day it drifted the sample text
 * would be publishable again with nothing to say so.
 */
final readonly class LegalPageTemplates
{
    /**
     * @var array<string, array<string, array{title: string, intro: string, sections: list<string>}>>
     */
    public const array PAGES = [
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

    /**
     * The draft a seeded legal page starts with.
     *
     * @param array{title: string, intro: string, sections: list<string>} $translation
     */
    public static function html(array $translation): string
    {
        $html = '<h1>'.htmlspecialchars($translation['title'], \ENT_QUOTES).'</h1>';
        $html .= '<p>'.htmlspecialchars($translation['intro'], \ENT_QUOTES).'</p>';

        foreach ($translation['sections'] as $section) {
            $html .= '<h2>'.htmlspecialchars($section, \ENT_QUOTES).'</h2><p></p>';
        }

        return $html;
    }

    /**
     * The sentences that only appear in a page nobody has written yet.
     *
     * They are the instructions the seeder puts in place of the text: whoever
     * writes the real page replaces them. The section headings are deliberately
     * left out, because "Hosting provider" is also a heading a real legal notice
     * has.
     *
     * @return list<string>
     */
    public static function sentences(): array
    {
        $sentences = [];

        foreach (self::PAGES as $translations) {
            foreach ($translations as $translation) {
                $sentences[] = $translation['intro'];
            }
        }

        return array_values(array_unique($sentences));
    }
}
