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

namespace TheliaCMS\Tests\Unit\Partial;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Partial\PartialProp;
use TheliaCMS\Partial\PartialRegistry;

/**
 * The registry is the allow list of the whole feature: a page names a block,
 * never a template.
 */
final class PartialRegistryTest extends TestCase
{
    public function testFindsAPartialByTheNameAPageStores(): void
    {
        $registry = new PartialRegistry([new FakePartial('cms-menu'), new FakePartial('latest-contents')]);

        self::assertTrue($registry->has('cms-menu'));
        self::assertSame('latest-contents', $registry->find('latest-contents')?->name());
    }

    public function testKnowsNothingOfAPartialNoModuleProvides(): void
    {
        $registry = new PartialRegistry([new FakePartial('cms-menu')]);

        self::assertFalse($registry->has('../../../templates/base'));
        self::assertNull($registry->find('latest-contents'));
    }

    public function testDescribesEverySettingToTheEditor(): void
    {
        $registry = new PartialRegistry([
            new FakePartial('news', [
                PartialProp::integer('count', 'How many', 3, 1, 12),
                PartialProp::reference('folder', 'Folder', '/sources/folders', required: false),
            ]),
        ]);

        $described = $registry->toEditor();

        self::assertCount(1, $described);
        self::assertSame('news', $described[0]['name']);
        self::assertSame(['count', 'folder'], array_column($described[0]['props'], 'name'));
        self::assertSame('integer', $described[0]['props'][0]['type']);
        self::assertSame('/sources/folders', $described[0]['props'][1]['source']);
    }

    /**
     * Two modules shipping the same name is a configuration mistake, not a
     * reason to render both: the last one registered wins, and the page still
     * renders one block.
     */
    public function testKeepsOneDefinitionPerName(): void
    {
        $registry = new PartialRegistry([new FakePartial('cms-menu'), new FakePartial('cms-menu')]);

        self::assertCount(1, $registry->all());
    }
}
