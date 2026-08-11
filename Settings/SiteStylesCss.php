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

namespace TheliaCMS\Settings;

use TheliaCMS\Builder\PageContentNormalizer;

/**
 * Writes the global style choices as a stylesheet.
 *
 * Everything is scoped to the container class every published piece of CMS
 * content carries, whatever the theme: the shop keeps the style of its theme,
 * the pages follow the choices made in the back office. The builder canvas
 * carries the same class on its wrapper, which is what makes an editor see in
 * the preview exactly what a visitor will get.
 *
 * Every value written here went through {@see ElementStyle}, which drops
 * anything that does not have the shape of its property. This class lays
 * values out; it does not need to defend against them.
 */
final readonly class SiteStylesCss
{
    private const string SCOPE = '.'.PageContentNormalizer::CONTAINER_CLASS;

    /** What each element answers to, inside the scope. */
    private const array SELECTORS = [
        'h1' => ['h1'],
        'h2' => ['h2'],
        'h3' => ['h3'],
        'h4' => ['h4'],
        'h5' => ['h5'],
        'h6' => ['h6'],
        'p' => ['p'],
        'a' => ['a'],
        'button' => ['button', '.btn', 'input[type="submit"]'],
    ];

    public function __construct(
        private FontStacks $stacks,
    ) {
    }

    /**
     * @param array<string, string> $fontUrls file name => public URL, for the
     *                                        uploaded fonts the choices use
     */
    public function build(SiteTypography $typography, array $fontUrls): string
    {
        $blocks = [];

        foreach ($typography->fontFiles() as $file) {
            if (isset($fontUrls[$file])) {
                $blocks[] = $this->fontFace($file, $fontUrls[$file]);
            }
        }

        foreach (self::SELECTORS as $element => $selectors) {
            $blocks = [...$blocks, ...$this->elementBlocks($typography->element($element), $selectors)];
        }

        return implode("\n", $blocks);
    }

    /**
     * The family an uploaded file is registered and referenced under: the file
     * name without its extension, as the person who named the file knows it.
     */
    public static function familyOf(string $file): string
    {
        return (string) preg_replace('/\.woff2$/i', '', basename($file));
    }

    private function fontFace(string $file, string $url): string
    {
        // The URL comes from the router and the family from a name checked to
        // be a bare .woff2 file name; quotes cannot appear in either.
        return \sprintf(
            "@font-face {\n    font-family: \"%s\";\n    src: url(\"%s\") format(\"woff2\");\n    font-display: swap;\n}",
            self::familyOf($file),
            $url,
        );
    }

    /**
     * @param list<string> $selectors
     *
     * @return list<string>
     */
    private function elementBlocks(ElementStyle $style, array $selectors): array
    {
        $scoped = implode(",\n", array_map(
            static fn (string $selector): string => self::SCOPE.' '.$selector,
            $selectors,
        ));

        $blocks = [];
        $declarations = $this->declarations($style);

        if ([] !== $declarations) {
            $blocks[] = \sprintf("%s {\n%s\n}", $scoped, implode("\n", $declarations));
        }

        if (null !== $style->hoverColor) {
            $hover = implode(",\n", array_map(
                static fn (string $selector): string => self::SCOPE.' '.$selector.':hover',
                $selectors,
            ));

            $blocks[] = \sprintf("%s {\n    color: %s;\n}", $hover, $style->hoverColor);
        }

        return $blocks;
    }

    /**
     * @return list<string>
     */
    private function declarations(ElementStyle $style): array
    {
        $declarations = [];

        if (null !== $style->font) {
            $declarations[] = '    font-family: '.$this->family($style->font).';';
        }

        foreach ([
            'font-size' => $style->size,
            'font-weight' => $style->weight,
            'line-height' => $style->lineHeight,
            'letter-spacing' => $style->letterSpacing,
            'text-transform' => $style->transform,
            'color' => $style->color,
            'text-decoration' => $style->underline,
            'background-color' => $style->background,
            'border-radius' => $style->radius,
        ] as $property => $value) {
            if (null !== $value) {
                $declarations[] = \sprintf('    %s: %s;', $property, $value);
            }
        }

        return $declarations;
    }

    private function family(string $font): string
    {
        if (str_starts_with($font, 'stack:')) {
            return $this->stacks->css(substr($font, 6)) ?? 'inherit';
        }

        // An uploaded file: its family, then something readable while it
        // loads or if it never does.
        return \sprintf('"%s", system-ui, sans-serif', self::familyOf(substr($font, 5)));
    }
}
