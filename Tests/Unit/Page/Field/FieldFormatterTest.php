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
use Symfony\Component\Translation\IdentityTranslator;
use TheliaCMS\Page\Field\FieldDefinition;
use TheliaCMS\Page\Field\FieldDefinitions;
use TheliaCMS\Page\Field\FieldFormatter;

/**
 * A value as a visitor reads it, in the language of the page.
 */
final class FieldFormatterTest extends TestCase
{
    public function testADurationIsWrittenInHoursAndMinutes(): void
    {
        $field = FieldDefinition::fromArray(['code' => 'cooking_time', 'kind' => 'duration']);

        self::assertSame('45 min', $this->formatter()->format($field, 45, 'fr_FR'));
        self::assertSame('2 h', $this->formatter()->format($field, 120, 'fr_FR'));
        self::assertSame('1 h 30 min', $this->formatter()->format($field, 90, 'fr_FR'));
    }

    public function testANumberIsWrittenTheWayTheLanguageWritesIt(): void
    {
        $field = FieldDefinition::fromArray(['code' => 'weight', 'kind' => 'decimal', 'unit' => 'kg']);

        self::assertSame('1,5 kg', $this->formatter()->format($field, 1.5, 'fr_FR'));
        self::assertSame('1.5 kg', $this->formatter()->format($field, 1.5, 'en_US'));
    }

    public function testTheRowsAreTheFieldsHoldingAValue(): void
    {
        $fields = FieldDefinitions::fromArray([
            ['code' => 'servings', 'kind' => 'integer', 'labels' => ['fr_FR' => 'Personnes']],
            ['code' => 'vegetarian', 'kind' => 'boolean', 'labels' => ['fr_FR' => 'Végétarien']],
            ['code' => 'origin', 'kind' => 'text'],
        ]);

        $rows = $this->formatter()->rows($fields, ['servings' => 4, 'vegetarian' => true, 'origin' => null], 'fr_FR');

        self::assertSame([
            ['code' => 'servings', 'label' => 'Personnes', 'value' => 4, 'text' => '4'],
            ['code' => 'vegetarian', 'label' => 'Végétarien', 'value' => true, 'text' => 'Yes'],
        ], $rows);
    }

    private function formatter(): FieldFormatter
    {
        return new FieldFormatter(new IdentityTranslator());
    }
}
