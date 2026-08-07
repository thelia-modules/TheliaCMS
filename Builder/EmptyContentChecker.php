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

/**
 * Tells whether a fragment about to be published would show anything.
 *
 * A page whose content is empty is stored with a null HTML column, which the
 * front reads as "no such page": the back office announces it published and the
 * visitor gets a 404. The check runs on the HTML as it comes out of the
 * sanitiser, so what is measured is what would actually be served.
 */
final readonly class EmptyContentChecker
{
    /**
     * Elements that show something without holding any text: a page made of a
     * single image, of a click-to-load video or of a separator is not empty.
     */
    private const array SELF_CONTAINED_ELEMENTS = [
        'img', 'picture', 'video', 'audio', 'iframe', 'svg', 'canvas',
        'embed', 'object', 'hr', 'input', 'select', 'textarea', 'button',
    ];

    /** Marker of a block rendered by the server at display time. */
    private const string PARTIAL_MARKER = 'data-cms-partial';

    public function isEmpty(?string $html): bool
    {
        if (null === $html || '' === trim($html)) {
            return true;
        }

        // A partial holds its content on the server, so the fragment shows
        // nothing of it here.
        if (str_contains($html, self::PARTIAL_MARKER)) {
            return false;
        }

        if ('' !== $this->textOf($html)) {
            return false;
        }

        return 1 !== preg_match('#<(?:'.implode('|', self::SELF_CONTAINED_ELEMENTS).')[\s/>]#i', $html);
    }

    /**
     * The text a visitor would read, whitespace and non-breaking spaces alike
     * removed.
     *
     * Script and style elements go first: `strip_tags()` keeps what is inside
     * them, so a leftover stylesheet would pass for a page of prose. Entities
     * are decoded next, so that a canvas holding a single `&nbsp;` reads as the
     * blank page it is.
     */
    private function textOf(string $html): string
    {
        $withoutCode = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $text = html_entity_decode(strip_tags($withoutCode), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return preg_replace('#[\s\x{00A0}]+#u', '', $text) ?? '';
    }
}
