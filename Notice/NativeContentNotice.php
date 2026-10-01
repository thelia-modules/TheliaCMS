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

namespace TheliaCMS\Notice;

use Thelia\Model\ContentQuery;
use TheliaCMS\TheliaCMS;

/**
 * What a shop that already has contents is told when the CMS arrives.
 *
 * Nothing is converted: the contents and folders stay where they were, and the
 * back-office menu now reaches them from the CMS section. A merchant who updates
 * a shop, or installs one with the demo, is told so once on the console and on
 * the Pages screen until they close the notice.
 *
 * Built with no dependency on purpose: `TheliaCMS::postActivation()` runs where
 * no container service can be asked for.
 */
final readonly class NativeContentNotice
{
    /** `module_config` row holding the merchant's "do not show this again". */
    public const string DISMISSED_SETTING = 'native_content_notice_dismissed';

    public function nativeContentCount(): int
    {
        return ContentQuery::create()->count();
    }

    public function isDismissed(): bool
    {
        return '1' === (string) TheliaCMS::getConfigValue(self::DISMISSED_SETTING, '0');
    }

    public function dismiss(): void
    {
        TheliaCMS::setConfigValue(self::DISMISSED_SETTING, '1');
    }

    /**
     * How many contents the banner of the Pages screen speaks of, or null when
     * it has nothing to say: no content, or the merchant closed it.
     */
    public function bannerCount(): ?int
    {
        if ($this->isDismissed()) {
            return null;
        }

        $count = $this->nativeContentCount();

        return $count > 0 ? $count : null;
    }

    /**
     * The line written on the console, in English like the rest of the console
     * output of Thelia, or null when the shop has no content.
     */
    public function consoleLine(): ?string
    {
        $count = $this->nativeContentCount();

        if (0 === $count) {
            return null;
        }

        return \sprintf(
            'TheliaCMS: %d existing content(s) stay in Folders, now reached from the CMS section of the back-office menu. Nothing is converted into CMS pages.',
            $count,
        );
    }
}
