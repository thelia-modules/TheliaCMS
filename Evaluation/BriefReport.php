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

namespace TheliaCMS\Evaluation;

/**
 * How much the runs of one brief agree, at the three levels of
 * `docs/plans/deterministic-skills.md`: structure, shape, text.
 */
final readonly class BriefReport
{
    /** At least four runs out of five give the same structure, and the same shape. */
    public const float AGREEMENT_TARGET = 0.8;

    /** The length of a section may vary by this much between runs. */
    public const float LENGTH_TOLERANCE = 0.3;

    /**
     * @param array<string, int>   $structures    the structures met, each with the number of runs that gave it, most frequent first
     * @param array<string, int>   $shapes        the same for the shapes
     * @param array<string, float> $lengthSpread  per section of the most frequent structure, `(longest - shortest) / longest`
     * @param list<string>         $unsteadyFacts the figures some runs write and others do not
     */
    public function __construct(
        public string $brief,
        public int $runs,
        public array $structures,
        public array $shapes,
        public array $lengthSpread,
        public array $unsteadyFacts,
        public ?float $averageCostUsd,
        public ?float $averageTurns,
    ) {
    }

    public function structureAgreement(): float
    {
        return 0 === $this->runs ? 0.0 : (max($this->structures ?: [0]) / $this->runs);
    }

    public function shapeAgreement(): float
    {
        return 0 === $this->runs ? 0.0 : (max($this->shapes ?: [0]) / $this->runs);
    }

    public function widestLengthSpread(): float
    {
        return max($this->lengthSpread ?: [0.0]);
    }

    /**
     * Whether the brief meets the targets: structure and shape agreed on,
     * lengths within the tolerance, the same facts in every run.
     */
    public function isSteady(): bool
    {
        return $this->structureAgreement() >= self::AGREEMENT_TARGET
            && $this->shapeAgreement() >= self::AGREEMENT_TARGET
            && $this->widestLengthSpread() <= self::LENGTH_TOLERANCE
            && [] === $this->unsteadyFacts;
    }
}
