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

namespace TheliaCMS\Tests\Unit\Settings;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheliaCMS\Settings\ElementStyle;
use TheliaCMS\Settings\FontStacks;

/**
 * Every value of an element style is written into a stylesheet served to
 * visitors. What matters is not what gets in, it is that nothing else does:
 * a value that does not have the shape of its property is dropped, never
 * escaped later.
 */
final class ElementStyleTest extends TestCase
{
    private FontStacks $stacks;

    protected function setUp(): void
    {
        $this->stacks = new FontStacks();
    }

    public function testItKeepsWellFormedValues(): void
    {
        $style = ElementStyle::fromArray([
            'font' => 'stack:garamond',
            'size' => '2.25rem',
            'weight' => '700',
            'lineHeight' => '1.2',
            'letterSpacing' => '-0.02em',
            'transform' => 'uppercase',
            'color' => '#111827',
            'hoverColor' => '#1d4ed8',
            'underline' => 'none',
            'background' => '#ffffff',
            'radius' => '8px',
        ], $this->stacks);

        self::assertSame([
            'font' => 'stack:garamond',
            'size' => '2.25rem',
            'weight' => '700',
            'lineHeight' => '1.2',
            'letterSpacing' => '-0.02em',
            'transform' => 'uppercase',
            'color' => '#111827',
            'hoverColor' => '#1d4ed8',
            'underline' => 'none',
            'background' => '#ffffff',
            'radius' => '8px',
        ], $style->toArray());
    }

    public function testNothingConfiguredIsEmpty(): void
    {
        self::assertTrue(ElementStyle::fromArray([], $this->stacks)->isEmpty());
        self::assertTrue(ElementStyle::none()->isEmpty());
    }

    #[DataProvider('hostileValues')]
    public function testWhatDoesNotHaveTheShapeOfItsPropertyIsDropped(array $values): void
    {
        self::assertTrue(ElementStyle::fromArray($values, $this->stacks)->isEmpty());
    }

    public static function hostileValues(): iterable
    {
        yield 'css injection through a size' => [['size' => '16px} body{display:none']];
        yield 'expression in a colour' => [['color' => 'url(javascript:alert(1))']];
        yield 'colour with a wrong length' => [['color' => '#12345']];
        yield 'unknown stack' => [['font' => 'stack:comic-sans']];
        yield 'font file escaping the directory' => [['font' => 'file:../../etc/passwd']];
        yield 'font file of another format' => [['font' => 'file:virus.svg']];
        yield 'free-text weight' => [['weight' => 'bolder; content:""']];
        yield 'transform outside the list' => [['transform' => 'full-width']];
        yield 'negative size' => [['size' => '-4px']];
        yield 'unit missing on a size' => [['size' => '16']];
        yield 'numbers where strings belong' => [['size' => 16, 'weight' => 700]];
    }

    public function testALetterSpacingMayBeNegative(): void
    {
        $style = ElementStyle::fromArray(['letterSpacing' => '-0.5px'], $this->stacks);

        self::assertSame(['letterSpacing' => '-0.5px'], $style->toArray());
    }

    public function testAnUploadedFontIsKeptByItsBareName(): void
    {
        $style = ElementStyle::fromArray(['font' => 'file:Recoleta-Bold.woff2'], $this->stacks);

        self::assertSame(['font' => 'file:Recoleta-Bold.woff2'], $style->toArray());
    }
}
