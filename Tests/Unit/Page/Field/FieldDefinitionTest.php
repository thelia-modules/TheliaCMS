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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheliaCMS\Page\Field\FieldDefinition;
use TheliaCMS\Page\Field\FieldKind;
use TheliaCMS\Page\Field\InvalidFieldsException;

/**
 * A field of a page type is read from a form, an import file or a column:
 * everything wrong is said at once, and a value is stored only in the shape
 * its kind takes.
 */
final class FieldDefinitionTest extends TestCase
{
    public function testADefinitionIsReadWithItsSettings(): void
    {
        $field = FieldDefinition::fromArray([
            'code' => 'servings',
            'kind' => 'integer',
            'labels' => ['fr_FR' => 'Nombre de personnes', 'en_US' => ' Servings '],
            'unit' => 'pers.',
            'required' => true,
            'min' => '1',
            'max' => 12,
        ]);

        self::assertSame('servings', $field->code);
        self::assertSame(FieldKind::Integer, $field->kind);
        self::assertSame('Servings', $field->label('en_US'));
        self::assertSame('Nombre de personnes', $field->label('de_DE'), 'A language without a label shows the first one.');
        self::assertSame('pers.', $field->unit);
        self::assertTrue($field->required);
        self::assertSame(1, $field->min);
        self::assertSame(12, $field->max);
    }

    public function testADefinitionSurvivesItsArray(): void
    {
        $field = FieldDefinition::fromArray(['code' => 'difficulty', 'kind' => 'choice', 'labels' => ['fr_FR' => 'Difficulté'], 'choices' => ['facile', 'moyen', 'facile', ' ']]);

        self::assertSame(['facile', 'moyen'], $field->choices, 'A word offered twice, or blank, is offered once or not at all.');
        self::assertEquals($field, FieldDefinition::fromArray($field->toArray()));
    }

    public function testEverythingWrongIsSaidAtOnce(): void
    {
        try {
            FieldDefinition::fromArray(['code' => 'Cooking time', 'kind' => 'choice', 'translated' => true, 'min' => 5, 'max' => 1]);
            self::fail('The definition is refused.');
        } catch (InvalidFieldsException $exception) {
            self::assertCount(4, $exception->problems, implode(' | ', $exception->problems));
        }
    }

    public function testAnUnknownKindIsRefused(): void
    {
        $this->expectException(InvalidFieldsException::class);

        FieldDefinition::fromArray(['code' => 'colour', 'kind' => 'colour']);
    }

    public function testNewIsNoCode(): void
    {
        self::assertFalse(FieldDefinition::isValidCode('new'), 'The address of the form that adds a field ends with it.');
        self::assertTrue(FieldDefinition::isValidCode('cooking_time'));
        self::assertFalse(FieldDefinition::isValidCode('2cooks'));
        self::assertFalse(FieldDefinition::isValidCode(str_repeat('a', 41)));
    }

    public function testOnlyAWordFieldIsTranslatedOrHasNoUnit(): void
    {
        $text = FieldDefinition::fromArray(['code' => 'origin', 'kind' => 'text', 'translated' => true, 'unit' => 'kg']);

        self::assertTrue($text->translated);
        self::assertNull($text->unit, 'A unit is written after a number only.');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, mixed, mixed}>
     */
    public static function acceptedValues(): iterable
    {
        yield 'a whole number' => [['kind' => 'integer'], '4', 4];
        yield 'a whole number as a float' => [['kind' => 'integer'], 4.0, 4];
        yield 'a decimal with a comma' => [['kind' => 'decimal'], '1,5', 1.5];
        yield 'a duration' => [['kind' => 'duration'], 90, 90];
        yield 'a text, its tags taken out' => [['kind' => 'text'], ' <b>Pâte</b>   feuilletée ', 'Pâte feuilletée'];
        yield 'a long text keeps its lines' => [['kind' => 'longtext'], "Une ligne\r\nDeux", "Une ligne\nDeux"];
        yield 'yes' => [['kind' => 'boolean'], 'true', true];
        yield 'no' => [['kind' => 'boolean'], '0', false];
        yield 'a word offered' => [['kind' => 'choice', 'choices' => ['facile', 'moyen']], 'moyen', 'moyen'];
        yield 'a date' => [['kind' => 'date'], '2026-12-24', '2026-12-24'];
        yield 'nothing' => [['kind' => 'integer'], '  ', null];
    }

    /**
     * @param array<string, mixed> $definition
     */
    #[DataProvider('acceptedValues')]
    public function testAValueIsStoredInTheShapeOfItsKind(array $definition, mixed $value, mixed $stored): void
    {
        self::assertSame($stored, FieldDefinition::fromArray(['code' => 'value', ...$definition])->normalize($value));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, mixed}>
     */
    public static function refusedValues(): iterable
    {
        yield 'a word for a number' => [['kind' => 'integer'], 'quatre'];
        yield 'a decimal for a whole number' => [['kind' => 'integer'], '4.5'];
        yield 'below the minimum' => [['kind' => 'integer', 'min' => 1], 0];
        yield 'above the maximum' => [['kind' => 'decimal', 'max' => 10], '10.5'];
        yield 'a negative duration' => [['kind' => 'duration'], -5];
        yield 'a text too long' => [['kind' => 'text', 'max' => 5], 'Gratin'];
        yield 'maybe' => [['kind' => 'boolean'], 'maybe'];
        yield 'a word not offered' => [['kind' => 'choice', 'choices' => ['facile']], 'difficile'];
        yield 'a day that does not exist' => [['kind' => 'date'], '2026-02-30'];
        yield 'a list for a text' => [['kind' => 'text'], ['a']];
    }

    /**
     * @param array<string, mixed> $definition
     */
    #[DataProvider('refusedValues')]
    public function testAValueItsKindDoesNotTakeIsRefused(array $definition, mixed $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FieldDefinition::fromArray(['code' => 'value', ...$definition])->normalize($value);
    }
}
