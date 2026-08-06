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

namespace TheliaCMS\Search;

/**
 * Reduces a published page to the words a visitor could search for.
 *
 * Extracting once at publish time keeps the front-office search off the HTML
 * columns entirely: it queries a full-text index over plain text.
 */
final readonly class SearchTextExtractor
{
    public function extract(?string $html): string
    {
        if (null === $html || '' === trim($html)) {
            return '';
        }

        // Elements whose content is code, not prose. strip_tags() drops the tags
        // but keeps what is between them, so a stylesheet or a script left in
        // the content would be indexed as words.
        $prose = preg_replace('#<(script|style|template|noscript)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;

        // Block-level markup carries a word boundary the tag stripping would
        // otherwise swallow, gluing "titleparagraph" into one token.
        $spaced = preg_replace('#<(br|/p|/h[1-6]|/li|/div|/section|/td|/th)\b[^>]*>#i', ' ', $prose) ?? $prose;

        $text = html_entity_decode(strip_tags($spaced), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return trim(preg_replace('#\s+#u', ' ', $text) ?? $text);
    }
}
