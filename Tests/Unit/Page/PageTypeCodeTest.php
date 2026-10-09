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

namespace TheliaCMS\Tests\Unit\Page;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheliaCMS\Page\PageTypeCode;

/**
 * A page type code becomes part of a template name: only lowercase letters,
 * digits and single hyphens get through.
 */
final class PageTypeCodeTest extends TestCase
{
    #[DataProvider('validCodes')]
    public function testAValidCodeIsKept(string $code): void
    {
        self::assertTrue(PageTypeCode::isValid($code));
        self::assertSame($code, PageTypeCode::orDefault($code));
    }

    #[DataProvider('invalidCodes')]
    public function testAnInvalidCodeIsTheDefaultType(string $code): void
    {
        self::assertFalse(PageTypeCode::isValid($code));
        self::assertSame(PageTypeCode::DEFAULT, PageTypeCode::orDefault($code));
    }

    #[DataProvider('validCodes')]
    public function testTheRouteRequirementAcceptsAValidCode(string $code): void
    {
        self::assertMatchesRegularExpression('#^'.PageTypeCode::ROUTE_REQUIREMENT.'$#D', $code);
    }

    #[DataProvider('invalidCodes')]
    public function testTheRouteRequirementRefusesAnInvalidCode(string $code): void
    {
        // The length is not the route's to check: the controller refuses a
        // code the site does not have.
        $matches = 1 === preg_match('#^'.PageTypeCode::ROUTE_REQUIREMENT.'$#D', $code);

        self::assertFalse($matches && \strlen($code) <= PageTypeCode::MAX_LENGTH);
    }

    public function testOnlyTheDefaultTypeIsKeptForGood(): void
    {
        self::assertFalse(PageTypeCode::isDeletable(PageTypeCode::DEFAULT));
        self::assertTrue(PageTypeCode::isDeletable('recipe'));
    }

    public function testNoCodeIsTheDefaultType(): void
    {
        self::assertSame(PageTypeCode::DEFAULT, PageTypeCode::orDefault(null));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validCodes(): iterable
    {
        yield 'a word' => ['recipe'];
        yield 'hyphenated, with a digit' => ['my-type-2'];
        yield 'the longest allowed' => [str_repeat('a', 50)];
        yield 'digits only' => ['2024'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'a path' => ['../x'];
        yield 'a slash' => ['news/recipe'];
        yield 'a dot' => ['a.b'];
        yield 'uppercase' => ['Recipe'];
        yield 'a space' => ['my type'];
        yield 'a leading hyphen' => ['-a'];
        yield 'a trailing hyphen' => ['a-'];
        yield 'a double hyphen' => ['a--b'];
        yield 'an accent' => ['recette-du-départ'];
        yield 'too long' => [str_repeat('a', 51)];
        yield 'a trailing newline' => ["recipe\n"];
    }
}
