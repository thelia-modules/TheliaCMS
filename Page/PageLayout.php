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
 * The three layouts a page could have before page types replaced them.
 *
 * @deprecated since 1.2.0, removed in 2.0.0: a page has a type instead
 *             (PublishedPage::$pageType), which picks its template. Each of
 *             these values is still a page type of the same code.
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
