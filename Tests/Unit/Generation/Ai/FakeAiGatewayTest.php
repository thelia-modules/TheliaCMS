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
use Thelia\Domain\Ai\AiFinishReason;
use Thelia\Domain\Ai\AiMessage;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\AiToolCall;
use TheliaCMS\Tests\Support\FakeAiGateway;

/**
 * The double the generators will be tested on.
 */
final class FakeAiGatewayTest extends TestCase
{
    public function testQueuedAnswersComeBackInOrderAndEveryRequestIsKept(): void
    {
        $first = FakeAiGateway::response('one');
        $second = FakeAiGateway::response('two');
        $gateway = (new FakeAiGateway())->answer($first)->answer($second);
        $request = self::request();

        self::assertSame($first, $gateway->complete($request));
        self::assertSame($second, $gateway->complete($request->withMessages(AiMessage::assistant('one'), AiMessage::user('again'))));
        self::assertCount(2, $gateway->requests);
        self::assertCount(3, $gateway->requests[1]->messages);
        self::assertSame(0, $gateway->remaining());
    }

    public function testAQueuedThrowableIsThrown(): void
    {
        $gateway = (new FakeAiGateway())->answer(new \DomainException('budget'));

        $this->expectException(\DomainException::class);

        $gateway->complete(self::request());
    }

    public function testAQueuedThrowableIsThrownByTheFirstIterationOfAStream(): void
    {
        $gateway = (new FakeAiGateway())->answer(new \DomainException('budget'));
        $stream = $gateway->stream(self::request());

        $this->expectException(\DomainException::class);

        foreach ($stream as $event) {
            self::fail('No event comes before the refusal.');
        }
    }

    public function testAnEmptyQueueFailsTheTestLoudly(): void
    {
        $this->expectException(\LogicException::class);

        (new FakeAiGateway())->complete(self::request());
    }

    public function testAResponseWithToolCallsStopsForThem(): void
    {
        $response = FakeAiGateway::response('', null, [new AiToolCall('call_1', 'search_pages', [])]);

        self::assertSame(AiFinishReason::ToolCalls, $response->finishReason);
        self::assertSame(AiFinishReason::MaxTokens, FakeAiGateway::response('x', null, [], AiFinishReason::MaxTokens)->finishReason);
    }

    public function testAStreamAnswersCompleteWithItsResponse(): void
    {
        $response = FakeAiGateway::response('abc');
        $gateway = (new FakeAiGateway())->streamed(['a', 'bc'], $response);

        self::assertSame($response, $gateway->complete(self::request()));
    }

    private static function request(): AiRequest
    {
        return new AiRequest('theliacms.test', [AiMessage::user('Hello')]);
    }
}
