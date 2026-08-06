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

namespace TheliaCMS\Page;

/**
 * Drives the wrapper the theme puts around the page content: whether it sits in
 * a container, and whether the header and footer are the full ones.
 */
enum PageLayout: string
{
    case Default = 'default';
    case FullWidth = 'full-width';
    case Landing = 'landing';

    public static function fromStorage(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Default;
    }
}
