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

namespace TheliaCMS\Builder;

use TheliaCMS\Install\LegalPageTemplates;

/**
 * Tells whether a page still holds the sample text it was seeded with.
 *
 * The legal pages a site owes its visitors are created on install, as drafts, so
 * that nobody ships without them. What they contain is instructions, not a legal
 * notice, and a site that puts them online tells its visitors things about
 * itself that are not true, in the sitemap and in search results as well.
 *
 * The measure is the sample sentence rather than the whole draft: a page where
 * somebody added a heading and stopped is still the sample text, and comparing
 * whole documents would let it through. A real page containing one of these
 * sentences word for word is not a case worth arranging for.
 */
final readonly class PlaceholderContentChecker
{
    /**
     * The sample sentence this content still carries, if any.
     */
    public function placeholderSentenceIn(?string $html): ?string
    {
        if (null === $html || '' === trim($html)) {
            return null;
        }

        $text = $this->textOf($html);

        foreach (LegalPageTemplates::sentences() as $sentence) {
            if (str_contains($text, $this->normalize($sentence))) {
                return $sentence;
            }
        }

        return null;
    }

    public function isPlaceholder(?string $html): bool
    {
        return null !== $this->placeholderSentenceIn($html);
    }

    /**
     * The text a visitor would read.
     *
     * Compared as text and not as markup: the sample draft goes through the
     * normaliser and the sanitiser on its way to publication, and an editor who
     * opens the page in the builder and saves it again gets the same sentence
     * wrapped in whatever markup the editor produces.
     */
    private function textOf(string $html): string
    {
        return $this->normalize(html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
    }

    /**
     * Typographic apostrophes are folded: the seeded French text uses `’` and an
     * editor passing through a form field or a copy and paste can turn it into
     * `'`, which is the same sentence.
     */
    private function normalize(string $text): string
    {
        $folded = str_replace(['’', '‘', '‚', '`', '´'], "'", $text);

        return trim((string) preg_replace('#[\s\x{00A0}]+#u', ' ', $folded));
    }
}
