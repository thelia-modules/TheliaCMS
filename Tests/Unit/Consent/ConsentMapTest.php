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

namespace TheliaCMS\Tests\Unit\Consent;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Consent\ConsentSettings;

/**
 * The map decides which Google signals a visitor's answer turns on. A signal
 * invented here is one Google ignores; a signal missing is a conversion never
 * counted.
 */
final class ConsentMapTest extends TestCase
{
    public function testReadsAMapOfVendorsAndTheirSignals(): void
    {
        $map = ConsentSettings::parseConsentMap('{"ga":["analytics_storage"],"ads":["ad_storage","ad_user_data"]}');

        self::assertSame([
            'ga' => ['analytics_storage'],
            'ads' => ['ad_storage', 'ad_user_data'],
        ], $map);
    }

    public function testDropsSignalsGoogleDoesNotKnow(): void
    {
        $map = ConsentSettings::parseConsentMap('{"ga":["analytics_storage","made_up_storage"]}');

        self::assertSame(['ga' => ['analytics_storage']], $map);
    }

    /**
     * A vendor left with no usable signal is dropped from the map, but that
     * does not free its snippets: gating reads the category off the snippet
     * itself, not off this map.
     */
    public function testDropsAVendorWhoseSignalsWereAllUnknown(): void
    {
        $map = ConsentSettings::parseConsentMap('{"ga":["analytics_storage"],"chat":["nonsense"]}');

        self::assertArrayNotHasKey('chat', $map);
        self::assertArrayHasKey('ga', $map);
    }

    public function testBrokenJsonFallsBackToTheDefaultRatherThanToNothing(): void
    {
        self::assertSame(ConsentSettings::defaultConsentMap(), ConsentSettings::parseConsentMap('{"ga":['));
        self::assertSame(ConsentSettings::defaultConsentMap(), ConsentSettings::parseConsentMap(''));
        self::assertSame(ConsentSettings::defaultConsentMap(), ConsentSettings::parseConsentMap('"a string"'));
    }

    public function testAnEmptyMapFallsBackToTheDefault(): void
    {
        self::assertSame(ConsentSettings::defaultConsentMap(), ConsentSettings::parseConsentMap('{}'));
        self::assertSame(ConsentSettings::defaultConsentMap(), ConsentSettings::parseConsentMap('{"":["ad_storage"]}'));
    }

    public function testIgnoresAVendorWhoseSignalsAreNotAList(): void
    {
        $map = ConsentSettings::parseConsentMap('{"ga":"analytics_storage","ads":["ad_storage"]}');

        self::assertSame(['ads' => ['ad_storage']], $map);
    }

    public function testTheDefaultMapOnlyUsesSignalsGoogleKnows(): void
    {
        foreach (ConsentSettings::defaultConsentMap() as $signals) {
            foreach ($signals as $signal) {
                self::assertContains($signal, ConsentSettings::CONSENT_MODE_SIGNALS);
            }
        }
    }
}
