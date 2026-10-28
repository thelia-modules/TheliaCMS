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

namespace TheliaCMS\Generation\Text;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use TheliaCMS\Content\Pipeline\PublishedContentSanitizer;

/**
 * Formatted text and nothing else: paragraphs, emphasis, lists, links,
 * subheadings, quotes. A container or a heading outside that set loses its tag
 * and keeps its text; an image, a table, a script or a frame goes, with what
 * it holds. The text of a field is written by the model, so it is filtered
 * before the editor sees it, and again by the page when it is saved.
 */
final readonly class TextSanitizer
{
    private const array TEXT_ELEMENTS = ['p', 'br', 'strong', 'em', 'b', 'i', 'u', 's', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote'];

    /** Unwrapped rather than dropped: what they hold is text the model meant. */
    private const array UNWRAPPED_ELEMENTS = ['div', 'span', 'section', 'article', 'header', 'footer', 'main', 'aside', 'h1', 'h5', 'h6', 'small', 'mark', 'code', 'sub', 'sup', 'figure', 'figcaption', 'font'];

    public function text(string $html): string
    {
        $config = (new HtmlSanitizerConfig())
            // The 20 000 bytes Symfony reads by default would cut a long text
            // in the middle of a sentence, without a word.
            ->withMaxInputLength(PublishedContentSanitizer::DEFAULT_MAX_LENGTH)
            ->allowElement('a', ['href', 'title'])
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            ->allowRelativeLinks();

        foreach (self::TEXT_ELEMENTS as $element) {
            $config = $config->allowElement($element);
        }

        foreach (self::UNWRAPPED_ELEMENTS as $element) {
            $config = $config->blockElement($element);
        }

        return trim((new HtmlSanitizer($config))->sanitize($html));
    }
}
