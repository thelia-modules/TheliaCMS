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

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Hands a set of stale tags to whatever sits in front of the site.
 *
 * With no purger installed this does nothing, which is the right answer for a
 * site with no shared cache: the pages were never `public` to begin with.
 * A purger that throws is logged and skipped, because failing to reach a CDN
 * must not turn publishing a page into an error the editor sees.
 */
final readonly class CachePurger
{
    /**
     * @param iterable<CachePurgerInterface> $purgers
     */
    public function __construct(
        #[AutowireIterator('thelia_cms.cache_purger')]
        private iterable $purgers,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<string> $tags
     */
    public function purge(array $tags): void
    {
        if ([] === $tags) {
            return;
        }

        foreach ($this->purgers as $purger) {
            try {
                $purger->purge($tags);
            } catch (\Throwable $throwable) {
                $this->logger->warning('A CMS cache purger failed: {reason}', [
                    'reason' => $throwable->getMessage(),
                    'purger' => $purger::class,
                    'tags' => $tags,
                ]);
            }
        }
    }
}
