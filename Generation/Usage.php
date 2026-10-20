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
 * The tokens a call consumed, summed over its turns: what the price is
 * computed from, and what the ledger keeps.
 */
final readonly class Usage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheReadTokens = 0,
        public int $cacheWriteTokens = 0,
    ) {
    }

    public function plus(\Anthropic\Messages\Usage $usage): self
    {
        return new self(
            $this->inputTokens + $usage->inputTokens,
            $this->outputTokens + $usage->outputTokens,
            $this->cacheReadTokens + ($usage->cacheReadInputTokens ?? 0),
            $this->cacheWriteTokens + ($usage->cacheCreationInputTokens ?? 0),
        );
    }
}
