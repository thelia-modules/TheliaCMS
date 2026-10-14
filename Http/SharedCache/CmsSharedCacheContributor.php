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

namespace TheliaCMS\Http\SharedCache;

use Thelia\Core\SharedCache\SharedCacheContributorInterface;
use TheliaCMS\TheliaCMS;

/**
 * Lets the shared cache of the core keep the pages of the module, the home page it
 * takes over included: they render the `cmspage` view, which is the same for every
 * visitor who arrives without a cookie.
 */
final readonly class CmsSharedCacheContributor implements SharedCacheContributorInterface
{
    public function readableResources(): array
    {
        return [];
    }

    public function shareableViews(): array
    {
        return [TheliaCMS::PAGE_VIEW];
    }
}
