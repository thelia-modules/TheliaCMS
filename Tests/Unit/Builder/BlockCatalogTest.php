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

namespace TheliaCMS\Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Builder\BlockCatalog;
use TheliaCMS\Builder\CatalogBlock;
use TheliaCMS\Builder\CatalogBlockProviderInterface;
use TheliaCMS\Settings\DisabledBlocks;

final class BlockCatalogTest extends TestCase
{
    public function testTheFirstProviderOfAnIdWins(): void
    {
        $catalog = new BlockCatalog([
            $this->provider('cms-hero', 'cms-logos'),
            $this->provider('cms-logos', 'shop-hours'),
        ]);

        self::assertSame(['cms-hero', 'cms-logos', 'shop-hours'], array_column($catalog->blocks('fr_FR'), 'id'));
        self::assertSame('first', $catalog->blocks('fr_FR')[1]->category, 'The module registers first, so an editor finds its blocks where they were.');
    }

    public function testEverythingIsOfferedUntilSomethingIsSwitchedOff(): void
    {
        $catalog = new BlockCatalog([$this->provider('cms-hero', 'cms-logos')]);

        self::assertSame(['cms-hero', 'cms-logos'], array_column($catalog->toEditor('fr_FR'), 'id'));
        self::assertSame(['cms-hero', 'cms-logos'], array_column($catalog->toEditor('fr_FR', DisabledBlocks::none()), 'id'));
    }

    public function testASwitchedOffBlockLeavesTheEditorButNotTheCatalogue(): void
    {
        $catalog = new BlockCatalog([$this->provider('cms-hero', 'cms-logos', 'cms-gallery')]);

        $offered = $catalog->toEditor('fr_FR', new DisabledBlocks(['cms-logos']));

        self::assertSame(['cms-hero', 'cms-gallery'], array_column($offered, 'id'));
        self::assertSame([0, 1], array_keys($offered), 'A list, or the JSON handed to the editor becomes an object.');
        self::assertSame(['cms-hero', 'cms-logos', 'cms-gallery'], array_column($catalog->blocks('fr_FR'), 'id'), 'The settings screen still has to list it.');
    }

    private function provider(string ...$ids): CatalogBlockProviderInterface
    {
        static $rank = 0;
        $category = 0 === $rank++ ? 'first' : 'later';

        return new class($ids, $category) implements CatalogBlockProviderInterface {
            /** @param list<string> $ids */
            public function __construct(private readonly array $ids, private readonly string $category)
            {
            }

            public function blocks(string $locale): array
            {
                return array_map(
                    fn (string $id): CatalogBlock => new CatalogBlock($id, ucfirst($id), '<section class="'.$id.'"></section>', $this->category),
                    $this->ids,
                );
            }
        };
    }
}
