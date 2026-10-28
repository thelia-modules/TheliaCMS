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
use TheliaCMS\Partial\Definition\BlockPartial;

/**
 * Drops cached fragments when what they render has changed.
 *
 * A dynamic block outlives the page it sits in: publishing a reusable block
 * changes pages that were published months ago and that nothing else is going
 * to touch. Whoever writes has to say so here, or the site keeps serving the
 * previous version until the lifetime of the fragment runs out.
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

    /**
     * Fragments of every reusable block.
     *
     * Not the fragments of the one block that changed: the key of a fragment
     * holds the settings of the placeholder, so finding the entries of a single
     * block would mean enumerating a cache that does not support it. Reusable
     * blocks are written rarely and read constantly, which makes dropping them
     * all the cheap side of the trade.
     */
    public function invalidateBlocks(): void
    {
        // The fragments only: the writer of the blocks drops from a shared
        // cache the pages that use the block that changed, and no others.
        $this->cache->invalidateTags([PartialFragmentRenderer::tagFor(BlockPartial::NAME)]);
    }
}
