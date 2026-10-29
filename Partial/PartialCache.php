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

namespace TheliaCMS\Partial;

use Symfony\Contracts\Cache\TagAwareCacheInterface;
use TheliaCMS\Http\CachePurger;
use TheliaCMS\Http\CacheTags;

/**
 * Drops cached fragments when what they render has changed.
 *
 * A dynamic block outlives the page it sits in: saving a form changes pages
 * that were published months ago and that nothing else is going to touch.
 * Whoever writes has to say so here, or the site keeps serving the previous
 * version until the lifetime of the fragment runs out.
 */
final readonly class PartialCache
{
    public function __construct(
        private TagAwareCacheInterface $cache,
        // The pages that show the block are in a shared cache with the
        // previous version of it, the fragment cache being only the first copy.
        private CachePurger $httpCache,
    ) {
    }

    /**
     * Everything rendered for one kind of block.
     */
    public function invalidate(string $partialName): void
    {
        $this->cache->invalidateTags([PartialFragmentRenderer::tagFor($partialName)]);
        $this->httpCache->purge([CacheTags::partial($partialName)]);
    }
}
