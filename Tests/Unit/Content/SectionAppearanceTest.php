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

namespace TheliaCMS\Tests\Unit\Content;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Content\InvalidDocumentException;
use TheliaCMS\Content\SectionAppearance;

final class SectionAppearanceTest extends TestCase
{
    public function testTheSettingsBecomeTheStyleOfTheSection(): void
    {
        $appearance = SectionAppearance::fromArray(['background' => '#F8D7D7', 'text' => '#3a1010', 'padding' => 'large', 'align' => 'center']);

        self::assertSame('#f8d7d7', $appearance->background, 'A colour is kept in lowercase, the way it is written back.');
        self::assertSame('background-color:#f8d7d7;color:#3a1010;padding-block:5rem;text-align:center', $appearance->style());
    }

    public function testEmptySettingsAreNoSettings(): void
    {
        $appearance = SectionAppearance::fromArray(['background' => '', 'text' => null, 'padding' => '', 'align' => null]);

        self::assertTrue($appearance->isEmpty(), 'An empty field of the form is the look of the component.');
        self::assertSame('', $appearance->style());
        self::assertSame([], $appearance->toArray());
    }

    public function testOnlyTheSettingsAndTheirValuesAreAccepted(): void
    {
        try {
            SectionAppearance::fromArray([
                'background' => 'red;position:fixed',
                'text' => 'url(https://example.com/x)',
                'padding' => 'huge',
                'align' => 'justify',
                'style' => 'display:none',
            ]);
            self::fail('A value that is not one of the settings is refused.');
        } catch (InvalidDocumentException $exception) {
            self::assertCount(5, $exception->problems, 'Every refused value is reported at once, for the model to correct them together.');
            self::assertStringContainsString('"style"', implode(' ', $exception->problems));
        }
    }
}
