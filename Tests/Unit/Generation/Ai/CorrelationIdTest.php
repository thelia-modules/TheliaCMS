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

namespace TheliaCMS\Tests\Unit\Generation\Ai;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\AiMessage;
use Thelia\Domain\Ai\AiRequest;
use TheliaCMS\Generation\Ai\CorrelationId;

/**
 * The identifier that groups the requests of one task in the log of the core.
 */
final class CorrelationIdTest extends TestCase
{
    public function testItNamesTheTaskAndIsDifferentEachTime(): void
    {
        $first = CorrelationId::new('theliacms', 'page', 42);

        self::assertMatchesRegularExpression('/^theliacms\.page\.42\.[0-9a-f]{8}$/', $first);
        self::assertNotSame($first, CorrelationId::new('theliacms', 'page', 42));
    }

    public function testTheCoreTakesWhatItBuilds(): void
    {
        $id = CorrelationId::new('theliacms', 'a type with spaces & symbols/'.str_repeat('x', 100), 7);

        self::assertLessThanOrEqual(64, \strlen($id));
        self::assertSame($id, (new AiRequest('theliacms.text', [AiMessage::user('x')], correlationId: $id))->correlationId);
    }
}
