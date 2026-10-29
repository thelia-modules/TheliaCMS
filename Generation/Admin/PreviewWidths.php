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

namespace TheliaCMS\Generation\Admin;

use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;

/**
 * The widths the preview of the content screen can be set to: the
 * breakpoints of the front theme, read where the toolkit of Flexy reads
 * them (the `--breakpoint-*` tokens of the nearest `assets/styles/variables.css`
 * of the theme and its parents), under the names the toolkit gives them.
 * A theme without the file gets the breakpoints of Flexy.
 */
final readonly class PreviewWidths
{
    public const string VARIABLES_FILE = 'assets/styles/variables.css';

    /** The breakpoints the toolkit names, and so offers; the others are left out. */
    private const array LABELS = [
        '2xs' => 'Mobile S',
        'xs' => 'Mobile M',
        'sm' => 'Mobile L',
        'md' => 'Tablet',
        'lg' => 'Small Desktop',
        'xl' => 'Desktop',
        '2xl' => 'Large Desktop',
    ];

    private const array FLEXY_BREAKPOINTS = ['2xs' => 20.0, 'xs' => 24.375, 'sm' => 40.0, 'md' => 48.0, 'lg' => 64.0, 'xl' => 85.375, '2xl' => 105.0];

    public function __construct(
        private TemplateHelperInterface $templates,
    ) {
    }

    /**
     * @return list<array{label: string, px: int}> narrowest first
     */
    public function widths(): array
    {
        $theme = $this->templates->getActiveFrontTemplate();

        return self::fromThemeDirectories(array_map(
            static fn (TemplateDefinition $template): string => $template->getAbsolutePath(),
            [$theme, ...array_values($theme->getParentList() ?? [])],
        ));
    }

    /**
     * @param list<string> $themeDirectories the theme first, then its parents
     *
     * @return list<array{label: string, px: int}>
     */
    public static function fromThemeDirectories(array $themeDirectories): array
    {
        $breakpoints = self::FLEXY_BREAKPOINTS;

        foreach ($themeDirectories as $directory) {
            $file = rtrim($directory, '/\\').\DIRECTORY_SEPARATOR.self::VARIABLES_FILE;

            if (is_file($file)) {
                $breakpoints = self::breakpointsIn((string) file_get_contents($file));

                break;
            }
        }

        asort($breakpoints);
        $widths = [];

        foreach ($breakpoints as $name => $rem) {
            if (isset(self::LABELS[$name])) {
                $widths[] = ['label' => self::LABELS[$name], 'px' => (int) round($rem * 16)];
            }
        }

        return $widths;
    }

    /**
     * @return array<string, float> rem by breakpoint name
     */
    private static function breakpointsIn(string $css): array
    {
        preg_match_all('/--breakpoint-([\w-]+):\s*([\d.]+)rem/', $css, $matches, \PREG_SET_ORDER);
        $breakpoints = [];

        foreach ($matches as $match) {
            $breakpoints[$match[1]] = (float) $match[2];
        }

        return $breakpoints;
    }
}
