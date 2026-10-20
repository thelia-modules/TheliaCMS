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

namespace TheliaCMS\Tests\Integration\Generation;

use Anthropic\Client;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TheliaCMS\Generation\ClaudeClientProviderInterface;

/**
 * The Claude API as the tests see it: answers queued in advance, in the wire
 * format of the API, and every request kept for the assertions. Nothing
 * reaches the network.
 */
final class FakeClaudeTransport implements ClientInterface, ClaudeClientProviderInterface
{
    /** @var list<array<string, mixed>> */
    private array $queue = [];

    /** @var list<array<string, mixed>> the decoded JSON body of every request, in order */
    public array $requests = [];

    /**
     * @param array<string, mixed> $body the wire form of a message
     */
    public function answer(array $body): self
    {
        $this->queue[] = $body;

        return $this;
    }

    /**
     * @param list<array<string, mixed>> $content
     * @param array<string, int>         $usage
     *
     * @return array<string, mixed>
     */
    public static function message(array $content, string $stopReason = 'end_turn', array $usage = [], ?array $stopDetails = null): array
    {
        return [
            'id' => 'msg_'.bin2hex(random_bytes(4)),
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5-5',
            'content' => $content,
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'stop_details' => $stopDetails,
            'usage' => $usage + ['input_tokens' => 100, 'output_tokens' => 50, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function text(string $text): array
    {
        return ['type' => 'text', 'text' => $text];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public static function toolUse(string $id, string $name, array $input): array
    {
        return ['type' => 'tool_use', 'id' => $id, 'name' => $name, 'input' => $input];
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = json_decode((string) $request->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        $body = array_shift($this->queue);

        if (null === $body) {
            return new Response(500, ['Content-Type' => 'application/json'], json_encode(['type' => 'error', 'error' => ['type' => 'api_error', 'message' => 'The fake transport has no answer left.']], \JSON_THROW_ON_ERROR));
        }

        return new Response(200, ['Content-Type' => 'application/json'], json_encode($body, \JSON_THROW_ON_ERROR));
    }

    public function client(): Client
    {
        return new Client(apiKey: 'sk-ant-test', requestOptions: ['transporter' => $this, 'maxRetries' => 0]);
    }
}
