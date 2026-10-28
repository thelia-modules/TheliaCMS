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

namespace TheliaCMS\Generation\Component;

use Thelia\Domain\Ai\AiProvenance;
use TheliaCMS\Content\Site\SiteComponent;
use TheliaCMS\Generation\Usage;

/**
 * A component the model wrote and the site checked, with what the call cost.
 * Nothing is kept yet: the editor looks at it first.
 */
final readonly class ComponentResult
{
    public function __construct(
        public SiteComponent $component,
        public string $model,
        public ?string $requestId,
        public Usage $usage,
        public float $costUsd,
        public int $durationMs,
        public int $turns,
        public int $logId,
        public ?AiProvenance $provenance = null,
    ) {
    }
}
