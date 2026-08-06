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
 * Turns what GrapesJS exports into a fragment a theme can drop inside its own
 * page.
 *
 * The editor treats its canvas as a whole document: it exports the wrapper as
 * a `<body>` element and styles it with `body { ... }` rules. Left alone, a
 * published page nests a second `<body>` inside the theme's one, and its CSS
 * restyles the whole site. Both are rewritten onto a single container class.
 */
final readonly class PageContentNormalizer
{
    /** Class the wrapper is rewritten to, in the HTML and in the CSS alike. */
    public const string CONTAINER_CLASS = 'cms-page-content';

    public function html(?string $html): ?string
    {
        if (null === $html || '' === trim($html)) {
            return $html;
        }

        $normalized = preg_replace_callback(
            '#<body\b([^>]*)>(.*)</body>#is',
            fn (array $matches): string => \sprintf(
                '<div class="%s">%s</div>',
                trim(self::CONTAINER_CLASS.' '.$this->classesOf($matches[1])),
                $matches[2],
            ),
            $html,
            1,
        );

        return $normalized ?? $html;
    }

    public function css(?string $css): ?string
    {
        if (null === $css || '' === trim($css)) {
            return $css;
        }

        // Only the `body` selector is rewritten: everything else GrapesJS
        // writes is a class or an id it generated for the page itself.
        $normalized = preg_replace(
            '#(^|[\s,{}])body\b#i',
            '$1.'.self::CONTAINER_CLASS,
            $css,
        );

        return $normalized ?? $css;
    }

    private function classesOf(string $attributes): string
    {
        if (1 !== preg_match('#\bclass\s*=\s*("|\')(.*?)\1#is', $attributes, $matches)) {
            return '';
        }

        return trim($matches[2]);
    }
}
