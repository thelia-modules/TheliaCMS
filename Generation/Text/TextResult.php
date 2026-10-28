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

namespace TheliaCMS\Generation\Text;

use TheliaCMS\Generation\Usage;

/**
 * A text the model wrote, filtered down to formatted text, with what the call
 * cost. Nothing is saved: the editor puts it in the field, or not.
 */
final readonly class TextResult
{
    public function __construct(
        public string $html,
        public string $model,
        public ?string $requestId,
        public Usage $usage,
        public float $costUsd,
        public int $durationMs,
        public int $turns,
        public int $logId,
    ) {
    }
}
