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

namespace TheliaCMS\Tests\Unit\Page\Field;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Page\Field\FieldDefinitions;
use TheliaCMS\Page\Field\InvalidFieldsException;

/**
 * The fields of a type, read whole: two fields never share a code, a column
 * that does not read defines nothing, and values are checked all at once.
 */
final class FieldDefinitionsTest extends TestCase
{
    public function testTwoFieldsDoNotShareACode(): void
    {
        $this->expectException(InvalidFieldsException::class);

        FieldDefinitions::fromArray([['code' => 'servings', 'kind' => 'integer'], ['code' => 'servings', 'kind' => 'text']]);
    }

    public function testAColumnThatDoesNotReadDefinesNoField(): void
    {
        self::assertTrue(FieldDefinitions::fromJson('{not json')->isEmpty());
        self::assertTrue(FieldDefinitions::fromJson('[{"code": "x", "kind": "colour"}]')->isEmpty());
        self::assertTrue(FieldDefinitions::fromJson(null)->isEmpty());
        self::assertNull(FieldDefinitions::none()->toJson(), 'No field leaves the column empty.');
    }

    public function testTheDefinitionsSurviveTheirColumn(): void
    {
        $fields = self::recipe();

        self::assertEquals($fields, FieldDefinitions::fromJson($fields->toJson()));
        self::assertSame(['servings', 'cooking_time', 'origin'], array_map(static fn ($field): string => $field->code, $fields->fields), 'The order is kept.');
    }

    public function testEveryValueIsCheckedAndNamedByItsLabel(): void
    {
        try {
            self::recipe()->normalize(['servings' => 'beaucoup', 'cooking_time' => -1, 'unknown' => 'x'], 'fr_FR');
            self::fail('The values are refused.');
        } catch (InvalidFieldsException $exception) {
            self::assertCount(2, $exception->problems);
            self::assertStringStartsWith('Nombre de personnes: ', $exception->problems[0]);
        }
    }

    public function testACodeTheTypeDoesNotDefineIsLeftOut(): void
    {
        self::assertSame(['servings' => 4, 'origin' => null], self::recipe()->normalize(['servings' => '4', 'origin' => '', 'colour' => 'red'], 'fr_FR'));
    }

    public function testTheRequiredFieldsLeftEmptyAreNamed(): void
    {
        $missing = self::recipe()->missingIn(['servings' => null, 'origin' => 'Bretagne']);

        self::assertSame(['servings'], array_map(static fn ($field): string => $field->code, $missing));
    }

    public function testTheTranslatedFieldsAreTheWordsWrittenAgain(): void
    {
        self::assertSame(['origin'], array_map(static fn ($field): string => $field->code, self::recipe()->filter(translated: true)->fields));
        self::assertCount(2, self::recipe()->filter(translated: false));
    }

    private static function recipe(): FieldDefinitions
    {
        return FieldDefinitions::fromArray([
            ['code' => 'servings', 'kind' => 'integer', 'labels' => ['fr_FR' => 'Nombre de personnes'], 'required' => true, 'min' => 1],
            ['code' => 'cooking_time', 'kind' => 'duration', 'labels' => ['fr_FR' => 'Temps de cuisson']],
            ['code' => 'origin', 'kind' => 'text', 'labels' => ['fr_FR' => 'Origine'], 'translated' => true],
        ]);
    }
}
