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

use TheliaCMS\TheliaCMS;

/**
 * Where the global styles of the site are kept.
 *
 * The typography lives in `module_config` like the other settings of the
 * module. The palette lives under the key the builder has always read
 * (`builder_palette`), so a site that configured its colours by hand before
 * this screen existed keeps them, and the pickers of the editor and the
 * stylesheet of the site can never disagree.
 */
final readonly class SiteStyles
{
    public const string TYPOGRAPHY = 'site_typography';
    public const string PALETTE = 'builder_palette';

    private const string COLOR = '/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i';

    public function __construct(
        private FontStacks $stacks,
        private SiteFonts $fonts,
    ) {
    }

    public function typography(): SiteTypography
    {
        $raw = json_decode((string) TheliaCMS::getConfigValue(self::TYPOGRAPHY, ''), true);

        return SiteTypography::fromArray(\is_array($raw) ? $raw : [], $this->stacks);
    }

    public function saveTypography(SiteTypography $typography): void
    {
        TheliaCMS::setConfigValue(
            self::TYPOGRAPHY,
            $typography->isEmpty() ? '' : (string) json_encode($typography->toArray()),
        );
    }

    /**
     * The colours offered by every picker of the builder. Empty means the
     * defaults of {@see \TheliaCMS\Builder\CmsBuilderConfig} apply.
     *
     * @return list<string>
     */
    public function palette(): array
    {
        $raw = json_decode((string) TheliaCMS::getConfigValue(self::PALETTE, ''), true);

        if (!\is_array($raw)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $colour): string => \is_string($colour) ? trim($colour) : '', $raw),
            static fn (string $colour): bool => 1 === preg_match(self::COLOR, $colour),
        ));
    }

    /**
     * @param list<string> $colours
     */
    public function savePalette(array $colours): void
    {
        $kept = array_values(array_filter(
            array_map(static fn (string $colour): string => trim($colour), $colours),
            static fn (string $colour): bool => 1 === preg_match(self::COLOR, $colour),
        ));

        TheliaCMS::setConfigValue(self::PALETTE, [] === $kept ? '' : (string) json_encode($kept));
    }

    /**
     * Changes whenever a choice or a font file changes: what the stylesheet
     * address carries so browsers refetch it exactly then, and keep it
     * otherwise.
     */
    public function version(): string
    {
        $state = TheliaCMS::getConfigValue(self::TYPOGRAPHY, '').'|'.implode(',', $this->fonts->all());

        return substr(hash('xxh3', $state), 0, 12);
    }
}
