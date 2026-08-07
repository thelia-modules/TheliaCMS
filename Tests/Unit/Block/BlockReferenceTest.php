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

namespace TheliaCMS\Tests\Unit\Block;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Block\BlockReference;

/**
 * Finding out whether a page still uses a reusable block.
 *
 * This is what stands between an editor and deleting a block that twenty pages
 * show, so both storage forms of the reference have to be recognised — and
 * block 1 must not be found in a page using block 11.
 */
final class BlockReferenceTest extends TestCase
{
    private BlockReference $reference;

    protected function setUp(): void
    {
        $this->reference = new BlockReference();
    }

    public function testFindsAReferenceInPublishedHtml(): void
    {
        $html = '<div data-cms-partial="cms-block" data-props="{&quot;block&quot;:7}"></div>';

        self::assertTrue($this->reference->isReferencedIn($html, 7));
    }

    public function testFindsAReferenceInTheEditorProject(): void
    {
        $project = '{"attributes":{"data-cms-partial":"cms-block","data-props":"{\\"block\\":7}"}}';

        self::assertTrue($this->reference->isReferencedIn($project, 7));
    }

    public function testFindsAReferenceStoredAsAString(): void
    {
        self::assertTrue($this->reference->isReferencedIn('{"block":"7"}', 7));
    }

    /**
     * The plain "does this text contain 7" version of this check reports block
     * 1 as used by every page holding block 11 — and then refuses to delete it.
     */
    public function testDoesNotMistakeALongerIdForThisOne(): void
    {
        self::assertFalse($this->reference->isReferencedIn('{"block":11}', 1));
        self::assertFalse($this->reference->isReferencedIn('{"block":711}', 7));
    }

    public function testIgnoresASettingOfAnotherBlockType(): void
    {
        self::assertFalse($this->reference->isReferencedIn('{"menu":7}', 7));
    }

    public function testFindsNothingInAPageWithoutBlocks(): void
    {
        self::assertFalse($this->reference->isReferencedIn('<p>Hello</p>', 7));
    }
}
