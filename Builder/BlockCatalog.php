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

namespace TheliaCMS\Builder;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Every block the editor offers: the ten shipped with the module, plus
 * whatever a project contributed through {@see CatalogBlockProviderInterface}.
 *
 * Blocks keep the order their providers return them in, and the module's own
 * come first — an editor who learns where the hero is should still find it
 * there after a project adds blocks of its own.
 */
final readonly class BlockCatalog
{
    public const string TAG = 'thelia_cms.catalog';

    /**
     * @param iterable<CatalogBlockProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(self::TAG)]
        private iterable $providers = [],
    ) {
    }

    /**
     * @return list<CatalogBlock>
     */
    public function blocks(string $locale): array
    {
        $blocks = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->blocks($locale) as $block) {
                // Two providers claiming the same id would give the editor two
                // entries doing different things under one name; the first one
                // registered wins.
                $blocks[$block->id] ??= $block;
            }
        }

        return array_values($blocks);
    }

    /**
     * @return list<array<string, string>>
     */
    public function toEditor(string $locale): array
    {
        return array_map(
            static fn (CatalogBlock $block): array => $block->toEditor(),
            $this->blocks($locale),
        );
    }
}
