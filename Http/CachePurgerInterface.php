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

namespace TheliaCMS\Http;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Tells the shared cache in front of the site that some pages are stale.
 *
 * The module ships no implementation that talks to anything: what a purge means
 * depends entirely on what is in front of the site, and guessing wrong is worse
 * than doing nothing. A project running Varnish, Fastly or Cloudflare
 * implements this and the tag is picked up.
 */
#[AutoconfigureTag('thelia_cms.cache_purger')]
interface CachePurgerInterface
{
    /**
     * @param list<string> $tags the cache tags to drop, as written in the `Cache-Tag` header
     */
    public function purge(array $tags): void;
}
