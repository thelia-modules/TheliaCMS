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

    /**
     * A source too long to load at once is searched by the editor: the flag
     * reaches it on the searchable setting, and stays off the others so the
     * select of an ordinary reference is left as it is.
     */
    /**
     * A page holding a block switched off under CMS > Settings still has to
     * preview it and edit its settings, so the block is described with the
     * others and only flagged.
     */
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
