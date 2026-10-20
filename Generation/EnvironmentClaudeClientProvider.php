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

namespace TheliaCMS\Generation;

use Anthropic\Client;

/**
 * The client of a live site: built on the key the environment holds in
 * `ANTHROPIC_API_KEY`. Without one, the generator says so rather than calling
 * an API that would answer 401.
 */
final class EnvironmentClaudeClientProvider implements ClaudeClientProviderInterface
{
    public const string VARIABLE = 'ANTHROPIC_API_KEY';

    private ?Client $client = null;

    public function client(): Client
    {
        if (null !== $this->client) {
            return $this->client;
        }

        $key = trim((string) ($_SERVER[self::VARIABLE] ?? $_ENV[self::VARIABLE] ?? getenv(self::VARIABLE) ?: ''));

        if ('' === $key) {
            throw GenerationUnavailableException::noKey();
        }

        return $this->client = new Client(apiKey: $key);
    }
}
