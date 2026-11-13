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

namespace TheliaCMS\Content\Site;

/**
 * The components of the site another one may draw with `cms_component()`:
 * those kept for the whole site, never a prototype.
 */
interface KeptSiteComponents
{
    public function kept(string $code): ?SiteComponent;

    /**
     * @return list<SiteComponent> every component of the site, the prototypes included, by code
     */
    public function all(): array;
}
