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
 * Hands the generator a client of the Claude API. An interface so that the
 * tests hand one that answers from recorded responses and never reaches the
 * network.
 */
interface ClaudeClientProviderInterface
{
    /**
     * @throws GenerationUnavailableException when the site has no key to call the API with
     */
    public function client(): Client;
}
