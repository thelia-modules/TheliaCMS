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

namespace TheliaCMS\Content\Pipeline;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use TheliaCMS\Content\DocumentRenderer;

/**
 * Filters what is about to go online.
 *
 * The person editing a page is not a trusted author: on a customer site they
 * are whoever the shop gave a back-office account to. What the editor produces
 * is therefore filtered on the server before it is published, so that no
 * client-side setting is the only thing standing between a form field and a
 * stored cross-site scripting payload.
 */
final readonly class PublishedContentSanitizer
{
    /**
     * An iframe calls a third party on every visit, before anyone has agreed
     * to anything: one written in a page is refused unless the author holds
     * the custom code resource.
     */
    private const array EMBED_ELEMENTS = ['iframe'];

    /**
     * Accessibility attributes kept on the way out.
     *
     * The default allow list of the sanitiser drops every `aria-*` attribute,
     * which quietly undid the one thing the block catalogue is careful about:
     * a section named by its own heading, an icon hidden from screen readers,
     * the current entry of a menu. None of them can carry script or a URL.
     */
    private const array ACCESSIBILITY_ATTRIBUTES = [
        'role',
        'aria-label',
        'aria-labelledby',
        'aria-describedby',
        'aria-hidden',
        'aria-current',
        'aria-expanded',
        'aria-controls',
        'aria-live',
        'lang',
        'dir',
    ];

    /**
     * The longest markup read, in bytes. The sanitizer of Symfony stops at
     * 20 000 by default and drops the rest without a word: a page of terms of
     * sale, or a recipe whose components are drawn with their classes, went
     * online cut in the middle of a sentence. A shop that publishes longer
     * pages still passes its own length to the service.
     */
    public const int DEFAULT_MAX_LENGTH = 1_000_000;

    public function __construct(
        private int $maxLength = self::DEFAULT_MAX_LENGTH,
    ) {
    }

    public function html(?string $html, bool $allowCustomCode = false): ?string
    {
        if (null === $html || '' === trim($html)) {
            return $html;
        }

        return $this->filterStyleAttributes($this->sanitizerFor($allowCustomCode)->sanitize($html));
    }

    /**
     * A `style` attribute is CSS too: what `css()` keeps out of a stylesheet
     * is kept out of it, so that markup cannot load from another host what a
     * stylesheet may not. The sanitizer quotes every attribute with `"` and
     * escapes its value, so the attribute is read back from its output.
     */
    private function filterStyleAttributes(string $html): string
    {
        return (string) preg_replace_callback(
            '/(\sstyle=")([^"]*)(")/i',
            fn (array $match): string => $match[1]
                .htmlspecialchars((string) $this->css(html_entity_decode($match[2], \ENT_QUOTES | \ENT_HTML5, 'UTF-8')), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8')
                .$match[3],
            $html,
        );
    }

    /**
     * How many elements of custom code the markup holds: what a sanitizer
     * without the right to them would take out.
     */
    public function customCodeCount(?string $html): int
    {
        if (null === $html || '' === $html) {
            return 0;
        }

        $count = 0;

        foreach (self::EMBED_ELEMENTS as $element) {
            $count += preg_match_all('/<'.$element.'\b/i', $html);
        }

        return $count;
    }

    /**
     * The stylesheet is not run through the HTML sanitizer — it never reaches
     * the parser as markup. What it must not carry is a request to a third
     * party (a privacy leak, and a bypass of the consent gate) or the legacy
     * Internet Explorer script vector.
     */
    public function css(?string $css): ?string
    {
        if (null === $css || '' === trim($css)) {
            return $css;
        }

        $filtered = preg_replace(
            [
                '#@import\b[^;]*;?#i',
                '#expression\s*\(#i',
                // Anything addressing another host, whether it names a scheme or
                // leaves it to the page (`//host/x` is remote too — only a lone
                // leading slash is a path on this site).
                '#url\(\s*([\'"]?)(?!/(?!/)|data:image/(?:png|jpe?g|gif|webp|avif);)[a-z0-9+.-]*:?//[^)]*\)#i',
                '#behavior\s*:[^;]*;?#i',
            ],
            ['', 'unsupported(', 'none', ''],
            $css,
        );

        return $filtered ?? '';
    }

    private function sanitizerFor(bool $allowCustomCode): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig())
            ->withMaxInputLength($this->maxLength)
            ->allowSafeElements()
            ->allowAttribute('class', '*')
            ->allowAttribute('id', '*')
            ->allowAttribute('style', '*')
            ->allowAttribute('loading', ['img', 'iframe'])
            ->allowAttribute('decoding', ['img'])
            ->allowAttribute('fetchpriority', ['img'])
            // Server-rendered partial blocks: the renderer validates the name
            // against its registry and the props against a schema.
            ->allowAttribute('data-cms-partial', '*')
            ->allowAttribute('data-props', '*')
            // The section an element renders, which the content screen
            // reads to point at it in the preview.
            ->allowAttribute(DocumentRenderer::SECTION_ATTRIBUTE, '*')
            // `open` is what an author toggles on a question that should start
            // expanded; without it a `<details>` always comes back closed.
            ->allowAttribute('open', ['details'])
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            // No data: URI — an SVG smuggled into one carries script.
            ->allowMediaSchemes(['https', 'http'])
            ->allowRelativeLinks()
            ->allowRelativeMedias();

        foreach (self::ACCESSIBILITY_ATTRIBUTES as $attribute) {
            $config = $config->allowAttribute($attribute, '*');
        }

        foreach (self::EMBED_ELEMENTS as $element) {
            $config = $allowCustomCode
                ? $config->allowElement($element, ['src', 'title', 'width', 'height', 'allow', 'allowfullscreen', 'loading'])
                : $config->blockElement($element);
        }

        return new HtmlSanitizer($config);
    }
}
