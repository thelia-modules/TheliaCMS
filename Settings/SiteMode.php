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
 * What kind of site this Thelia runs.
 *
 * `Commerce` is the default and changes nothing at all: a shop with a CMS in it
 * behaves exactly as it did before the module was installed.
 */
enum SiteMode: string
{
    case Commerce = 'commerce';
    case Showcase = 'vitrine';

    public static function fromStorage(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Commerce;
    }

    public function isShowcase(): bool
    {
        return self::Showcase === $this;
    }
}
