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
use TheliaCMS\Content\ProvenanceMark;
use TheliaCMS\Tests\Support\FakeAiGateway;

final class ProvenanceMarkTest extends TestCase
{
    public function testTheMarkIsStoredAsTheCoreWritesIt(): void
    {
        $json = ProvenanceMark::encode(FakeAiGateway::provenance());

        self::assertSame('{"ai_generated":true,"usage":"theliacms.page_generation","model":"fake-model","generated_at":"2026-10-28T10:00:00+00:00","log_id":1}', $json);
        self::assertNull(ProvenanceMark::encode(null));
    }

    public function testWhatIsReadBackIsAValidMarkOrNothing(): void
    {
        $json = (string) ProvenanceMark::encode(FakeAiGateway::provenance());

        self::assertSame($json, ProvenanceMark::normalize($json));
        self::assertSame($json, ProvenanceMark::normalize(json_decode($json, true)), 'An array read from a file.');
        self::assertSame($json, ProvenanceMark::normalize($json."\n"), 'Written again in the form the core gives it.');
        self::assertNull(ProvenanceMark::normalize('not json'));
        self::assertNull(ProvenanceMark::normalize('{"ai_generated":false,"usage":"x","model":"y","generated_at":"2026-10-28T10:00:00+00:00","log_id":1}'));
        self::assertNull(ProvenanceMark::normalize('{"ai_generated":true,"usage":"theliacms.page_generation","model":"m"}'), 'A field of the core is missing.');
        self::assertNull(ProvenanceMark::normalize(null));
        self::assertNull(ProvenanceMark::normalize(42));
    }
}
