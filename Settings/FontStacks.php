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

/**
 * Font stacks made only of what visitors already have installed.
 *
 * Each stack lines up the same design across platforms — the macOS face
 * first, then its Windows, Linux and Android equivalents — so nothing is
 * downloaded and nothing is requested from a third party. A site that wants
 * its own face uploads a file instead; these are the choices that cost
 * nothing.
 */
final readonly class FontStacks
{
    /**
     * label: source string translated where the stack is offered.
     *
     * @var array<string, array{label: string, css: string}>
     */
    private const array STACKS = [
        'system' => [
            'label' => 'System',
            'css' => 'system-ui, sans-serif',
        ],
        'neo-grotesque' => [
            'label' => 'Modern sans serif',
            'css' => 'Inter, Roboto, "Helvetica Neue", "Arial Nova", "Nimbus Sans", Arial, sans-serif',
        ],
        'humanist' => [
            'label' => 'Humanist sans serif',
            'css' => 'Seravek, "Gill Sans Nova", Ubuntu, Calibri, "DejaVu Sans", source-sans-pro, sans-serif',
        ],
        'geometric' => [
            'label' => 'Geometric sans serif',
            'css' => 'Avenir, Montserrat, Corbel, "URW Gothic", source-sans-pro, sans-serif',
        ],
        'transitional' => [
            'label' => 'Classic serif',
            'css' => 'Charter, "Bitstream Charter", "Sitka Text", Cambria, serif',
        ],
        'garamond' => [
            'label' => 'Garamond serif',
            'css' => 'Garamond, Baskerville, "Baskerville Old Face", "Hoefler Text", "Times New Roman", serif',
        ],
        'slab' => [
            'label' => 'Slab serif',
            'css' => 'Rockwell, "Rockwell Nova", "Roboto Slab", "DejaVu Serif", "Sitka Small", serif',
        ],
        'didone' => [
            'label' => 'Didone serif',
            'css' => 'Didot, "Bodoni MT", "Noto Serif Display", "URW Palladio L", P052, Sylfaen, serif',
        ],
        'rounded' => [
            'label' => 'Rounded',
            'css' => 'ui-rounded, "Hiragino Maru Gothic ProN", Quicksand, Comfortaa, Manjari, "Arial Rounded MT", "Arial Rounded MT Bold", Calibri, source-sans-pro, sans-serif',
        ],
        'monospace' => [
            'label' => 'Monospace',
            'css' => 'ui-monospace, "Cascadia Code", "Source Code Pro", Menlo, Consolas, "DejaVu Sans Mono", monospace',
        ],
    ];

    public function has(string $key): bool
    {
        return isset(self::STACKS[$key]);
    }

    public function css(string $key): ?string
    {
        return self::STACKS[$key]['css'] ?? null;
    }

    /**
     * @return array<string, string> key => label, in the order they are offered
     */
    public function labels(): array
    {
        return array_map(static fn (array $stack): string => $stack['label'], self::STACKS);
    }
}
