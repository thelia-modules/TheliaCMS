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

use Thelia\Core\SharedCache\SharedCacheInvalidator;
use Thelia\Core\SharedCache\SharedCacheKeyCollector;
use Thelia\Core\SharedCache\SharedCacheMode;
use TheliaCMS\Http\CachePurgerInterface;

/**
 * The shared cache of the core, seen from the module.
 *
 * Once the shop turns it on, the core decides whether a page may be kept and writes
 * its headers; the module only names what the page shows, with its own tags, so that
 * its purges reach the pages the core kept. As a purger it hands every tag the module
 * drops to the core, which sends it to the proxy when the request ends.
 */
final readonly class CoreSharedCache implements CachePurgerInterface
{
    public function __construct(
        private SharedCacheKeyCollector $keys,
        private SharedCacheInvalidator $invalidator,
    ) {
    }

    public function isActive(): bool
    {
        return SharedCacheMode::DISABLED !== SharedCacheMode::fromShopConfiguration();
    }

    /**
     * @param list<string> $tags
     */
    public function tag(array $tags): void
    {
        $this->keys->tag(...$tags);
    }

    public function purge(array $tags): void
    {
        $this->invalidator->purge($tags);
    }
}
