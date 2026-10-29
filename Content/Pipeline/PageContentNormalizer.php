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

/**
 * Holds a rendered document to one container, the one the stylesheets of the
 * site and the heading check know.
 *
 * A fragment written as a whole document — the markup of a 1.x export, a
 * piece of free text pasted with its `<body>` — is rewritten onto the
 * container class rather than nesting a second body inside the theme's one.
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
                // Deduplicated: the canvas wrapper carries the container class
                // too, so the preview obeys the global styles of the site.
                implode(' ', array_unique(array_filter(explode(' ', self::CONTAINER_CLASS.' '.$this->classesOf($matches[1]))))),
                $matches[2],
            ),
            $html,
            1,
        );

        return $normalized ?? $html;
    }

    private function classesOf(string $attributes): string
    {
        if (1 !== preg_match('#\bclass\s*=\s*("|\')(.*?)\1#is', $attributes, $matches)) {
            return '';
        }

        return trim($matches[2]);
    }
}
