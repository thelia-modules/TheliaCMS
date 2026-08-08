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

namespace TheliaCMS\Page\Admin;

final class PlaceholderPageContentException extends \RuntimeException
{
    public static function for(int $pageId, string $locale, string $sentence): self
    {
        return new self(\sprintf('CMS page #%d still holds the seeded sample text in %s: "%s".', $pageId, $locale, $sentence));
    }
}
