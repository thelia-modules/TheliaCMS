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
 * What a call costs, from the tokens it used: the list prices of the Claude
 * API in US dollars per million tokens, as published on 2026-10-06. An
 * estimate for the ledger of the site, not an invoice: the prices move, and
 * a model this table does not know is counted at the price of the most
 * expensive one it knows.
 */
final readonly class Pricing
{
    /**
     * Per million tokens: input, output, cache read, cache write.
     *
     * @var array<string, array{float, float, float, float}>
     */
    private const array PER_MILLION = [
        'claude-opus-5-5' => [4.0, 20.0, 0.20, 5.0],
        'claude-opus-5' => [5.0, 25.0, 0.50, 6.25],
        'claude-sonnet-5-5' => [2.0, 10.0, 0.20, 2.5],
        'claude-sonnet-5' => [2.0, 10.0, 0.20, 2.5],
        'claude-haiku-5-5' => [0.10, 0.50, 0.01, 0.125],
        'claude-fable-5-1' => [10.0, 50.0, 0.25, 12.5],
    ];

    public function cost(string $model, Usage $usage): float
    {
        [$input, $output, $cacheRead, $cacheWrite] = self::PER_MILLION[$model] ?? self::PER_MILLION['claude-fable-5-1'];

        return round(
            ($usage->inputTokens * $input + $usage->outputTokens * $output + $usage->cacheReadTokens * $cacheRead + $usage->cacheWriteTokens * $cacheWrite) / 1_000_000,
            6,
        );
    }
}
