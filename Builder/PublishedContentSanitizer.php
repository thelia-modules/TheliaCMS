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

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

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
     * Third-party embeds are served by the partial blocks, which render a
     * click-to-load facade. An iframe pasted in the canvas is refused unless
     * the author holds the custom code resource.
     */
    private const array EMBED_ELEMENTS = ['iframe'];

    public function html(?string $html, bool $allowCustomCode = false): ?string
    {
        if (null === $html || '' === trim($html)) {
            return $html;
        }

        return $this->sanitizerFor($allowCustomCode)->sanitize($html);
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
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            // No data: URI — an SVG smuggled into one carries script.
            ->allowMediaSchemes(['https', 'http'])
            ->allowRelativeLinks()
            ->allowRelativeMedias();

        foreach (self::EMBED_ELEMENTS as $element) {
            $config = $allowCustomCode
                ? $config->allowElement($element, ['src', 'title', 'width', 'height', 'allow', 'allowfullscreen', 'loading'])
                : $config->blockElement($element);
        }

        return new HtmlSanitizer($config);
    }
}
