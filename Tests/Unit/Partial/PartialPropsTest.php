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
use Symfony\Component\Translation\IdentityTranslator;
use TheliaCMS\Partial\MissingPartialPropException;
use TheliaCMS\Partial\PartialProp;
use TheliaCMS\Partial\PartialProps;

/**
 * The settings of a dynamic block come out of the page itself, so they are
 * whatever ended up in a `data-props` attribute — imported, hand-edited, or
 * written by someone who found the HTML panel. Nothing gets through untyped.
 */
final class PartialPropsTest extends TestCase
{
    private PartialProps $props;

    protected function setUp(): void
    {
        $this->props = new PartialProps(new IdentityTranslator());
    }

    public function testDropsSettingsThePartialDoesNotDeclare(): void
    {
        $definition = new FakePartial('news', [PartialProp::integer('count', 'How many', 3, 1, 12)]);

        $values = $this->props->validate(['count' => 5, 'template' => '../../../etc/passwd'], $definition);

        self::assertSame(['count' => 5], $values);
    }

    public function testFallsBackToTheDefaultWhenASettingIsAbsent(): void
    {
        $definition = new FakePartial('news', [PartialProp::integer('count', 'How many', 3, 1, 12)]);

        self::assertSame(['count' => 3], $this->props->validate([], $definition));
    }

    public function testKeepsANumberInsideItsBounds(): void
    {
        $definition = new FakePartial('news', [PartialProp::integer('count', 'How many', 3, 1, 12)]);

        self::assertSame(['count' => 12], $this->props->validate(['count' => 900], $definition));
        self::assertSame(['count' => 1], $this->props->validate(['count' => -4], $definition));
    }

    public function testFallsBackWhenANumberIsNotOne(): void
    {
        $definition = new FakePartial('news', [PartialProp::integer('count', 'How many', 3, 1, 12)]);

        self::assertSame(['count' => 3], $this->props->validate(['count' => 'all of them'], $definition));
    }

    public function testTrimsAndCutsTextToItsMaximumLength(): void
    {
        $definition = new FakePartial('news', [PartialProp::text('heading', 'Heading', max: 5)]);

        self::assertSame(['heading' => 'Hello'], $this->props->validate(['heading' => '  Hello world '], $definition));
    }

    public function testReadsABooleanTheWayAnAttributeCarriesIt(): void
    {
        $definition = new FakePartial('news', [PartialProp::boolean('dates', 'Show the dates', true)]);

        self::assertSame(['dates' => false], $this->props->validate(['dates' => '0'], $definition));
        self::assertSame(['dates' => true], $this->props->validate(['dates' => '1'], $definition));
        // Neither true nor false: the block keeps behaving as it was set up to.
        self::assertSame(['dates' => true], $this->props->validate(['dates' => 'maybe'], $definition));
    }

    public function testRefusesAChoiceThatIsNotOffered(): void
    {
        $definition = new FakePartial('news', [PartialProp::choice('layout', 'Layout', ['grid' => 'Grid', 'list' => 'List'], 'grid')]);

        self::assertSame(['layout' => 'list'], $this->props->validate(['layout' => 'list'], $definition));
        self::assertSame(['layout' => 'grid'], $this->props->validate(['layout' => 'carousel'], $definition));
    }

    /**
     * A reference is a primary key. Anything that is not a positive integer is
     * not one, whatever it may be trying to be.
     */
    public function testAcceptsOnlyAPositiveIntegerAsAReference(): void
    {
        $definition = new FakePartial('menu', [PartialProp::reference('menu', 'Menu', '/sources/menus', required: false)]);

        self::assertSame(['menu' => 7], $this->props->validate(['menu' => '7'], $definition));
        self::assertNull($this->props->validate(['menu' => '7 OR 1=1'], $definition)['menu']);
        self::assertNull($this->props->validate(['menu' => 0], $definition)['menu']);
        self::assertNull($this->props->validate(['menu' => -3], $definition)['menu']);
        self::assertNull($this->props->validate(['menu' => 2.5], $definition)['menu']);
    }

    public function testRefusesAnArrayWhereAScalarIsExpected(): void
    {
        $definition = new FakePartial('news', [PartialProp::text('heading', 'Heading', 'News')]);

        self::assertSame(['heading' => 'News'], $this->props->validate(['heading' => ['a', 'b']], $definition));
    }

    public function testSaysWhichRequiredSettingIsMissing(): void
    {
        $definition = new FakePartial('cms-menu', [PartialProp::reference('menu', 'Menu', '/sources/menus')]);

        $this->expectException(MissingPartialPropException::class);
        $this->expectExceptionMessage('Choose a value for "Menu"');

        $this->props->validate([], $definition);
    }
}
