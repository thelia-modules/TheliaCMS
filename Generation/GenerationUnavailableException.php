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

/**
 * The generator cannot be called at all on this site: no key, or the API
 * cannot be reached.
 */
final class GenerationUnavailableException extends GenerationException
{
    public static function noKey(): self
    {
        return self::because(\sprintf('The site has no key for the Claude API: set %s in the environment.', EnvironmentClaudeClientProvider::VARIABLE));
    }
}
