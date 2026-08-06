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

use OpenStudio\PageBuilderBundle\Contract\PageBuilderConfigProviderInterface;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use TheliaCMS\TheliaCMS;

/**
 * What the editor needs to know about the site it edits: the stylesheet its
 * canvas renders with, the colours it may offer, and the screen widths it
 * previews.
 */
final readonly class CmsBuilderConfig implements PageBuilderConfigProviderInterface
{
    /**
     * Compiled stylesheet of the front-office theme, as the asset mapper knows
     * it. Themes that build their CSS some other way set `builder_stylesheet`.
     */
    private const string THEME_STYLESHEET = 'styles/app.css';

    /**
     * Neutral, contrast-checked defaults: every colour reaches 4.5:1 against
     * white or against black, so a text block cannot come out unreadable
     * before a project swaps in its own palette.
     */
    private const array DEFAULT_PALETTE = [
        '#111827', '#374151', '#6b7280', '#e5e7eb', '#ffffff',
        '#1d4ed8', '#047857', '#b45309', '#b91c1c', '#6d28d9',
    ];

    public function __construct(
        private AssetMapperInterface $assetMapper,
    ) {
    }

    public function getConfig(?string $context = null): array
    {
        return [
            'appStylesheet' => $this->themeStylesheet(),
            'icons' => [],
            'palette' => $this->palette(),
            // No block asks the server to render a fragment for it yet, so the
            // editor is given no endpoint to call.
            'renderTemplateEndpoint' => null,
        ];
    }

    /**
     * Canvas widths the editor previews, taken from the theme breakpoints.
     *
     * GrapesJS writes the styles of a device into a `max-width` media query,
     * where a Tailwind theme is written `min-width`. Picking each width one
     * pixel below a theme breakpoint keeps the two from overlapping: the
     * tablet canvas stops exactly where `lg:` starts, the mobile one exactly
     * where `md:` starts.
     *
     * @return array<string, mixed>
     */
    public function editorOptions(): array
    {
        return [
            // GrapesJS otherwise prepends a reset of its own (`*` and `body`)
            // to the page stylesheet, which would restyle the whole site the
            // page is published into. Resets belong to the theme.
            'protectedCss' => '',
            'deviceManager' => [
                'devices' => [
                    ['id' => 'desktop', 'name' => 'Desktop', 'width' => ''],
                    ['id' => 'tablet', 'name' => 'Tablet', 'width' => '768px', 'widthMedia' => '1023px'],
                    ['id' => 'mobile', 'name' => 'Mobile', 'width' => '375px', 'widthMedia' => '767px'],
                ],
            ],
        ];
    }

    private function themeStylesheet(): ?string
    {
        $configured = (string) TheliaCMS::getConfigValue('builder_stylesheet', '');

        if ('' !== $configured) {
            return $configured;
        }

        return $this->assetMapper->getAsset(self::THEME_STYLESHEET)?->publicPath;
    }

    /**
     * @return list<string>
     */
    private function palette(): array
    {
        $configured = (string) TheliaCMS::getConfigValue('builder_palette', '');

        if ('' === $configured) {
            return self::DEFAULT_PALETTE;
        }

        $colours = json_decode($configured, true);

        return \is_array($colours) ? array_values(array_filter($colours, \is_string(...))) : self::DEFAULT_PALETTE;
    }
}
