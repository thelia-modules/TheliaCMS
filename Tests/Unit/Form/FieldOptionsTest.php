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

namespace TheliaCMS\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Form\FieldOptions;
use TheliaCMS\Form\FieldType;

/**
 * The options column is written by the back office today and by an import
 * tomorrow, so it is read like anything else that comes from outside.
 */
final class FieldOptionsTest extends TestCase
{
    public function testReadsTheHeightOfATextarea(): void
    {
        self::assertSame(8, FieldOptions::decode('{"rows":8}')->rows);
    }

    public function testFallsBackToADefaultHeightWhenThereIsNothingToRead(): void
    {
        self::assertSame(4, FieldOptions::decode(null)->rows);
        self::assertSame(4, FieldOptions::decode('')->rows);
        self::assertSame(4, FieldOptions::decode('not json')->rows);
        self::assertSame(4, FieldOptions::decode('"a string"')->rows);
    }

    public function testKeepsTheHeightWithinWhatIsUsable(): void
    {
        self::assertSame(2, FieldOptions::decode('{"rows":-5}')->rows);
        self::assertSame(20, FieldOptions::decode('{"rows":900}')->rows);
    }

    public function testIgnoresSettingsItDoesNotKnow(): void
    {
        $decoded = FieldOptions::decode('{"rows":6,"template":"../../../etc/passwd"}');

        self::assertSame('{"rows":6}', $decoded->encode());
    }

    public function testRoundTripsThroughStorage(): void
    {
        self::assertSame(7, FieldOptions::decode((new FieldOptions(rows: 7))->encode())->rows);
    }

    public function testAnUnknownStoredTypeReadsAsPlainText(): void
    {
        self::assertSame(FieldType::Text, FieldType::fromStorage('signature'));
        self::assertSame(FieldType::Text, FieldType::fromStorage(null));
    }

    public function testOnlyDropDownsAndRadiosOfferWrittenAnswers(): void
    {
        self::assertTrue(FieldType::Select->hasChoices());
        self::assertTrue(FieldType::Radio->hasChoices());
        self::assertFalse(FieldType::Text->hasChoices());
        self::assertFalse(FieldType::Consent->hasChoices());
    }

    public function testConsentIsATickBoxLikeACheckbox(): void
    {
        self::assertTrue(FieldType::Consent->isTickBox());
        self::assertTrue(FieldType::Checkbox->isTickBox());
        self::assertFalse(FieldType::Date->isTickBox());
    }
}
