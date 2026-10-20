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

use TheliaCMS\Content\Document;

/**
 * What a call to the generator produced: a document the validator accepted,
 * and what it took to get it.
 */
final readonly class GenerationResult
{
    public function __construct(
        public Document $document,
        public string $model,
        public ?string $requestId,
        public Usage $usage,
        public float $costUsd,
        public int $durationMs,
        public int $turns,
        public ?int $logId = null,
    ) {
    }
}
