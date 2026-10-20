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
 * The typography of the site: the fonts and the element styles the pages
 * follow, stored as a setting of the module and served as a stylesheet.
 */
final readonly class SiteStyles
{
    public const string TYPOGRAPHY = 'site_typography';

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
