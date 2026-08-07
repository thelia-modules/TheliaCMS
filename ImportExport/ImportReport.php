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

namespace TheliaCMS\ImportExport;

/**
 * What an import did, and what it could not do.
 *
 * An import that answers "done" and leaves three menu entries pointing nowhere
 * is worse than one that fails: the site looks imported. Everything skipped or
 * approximated is counted here and printed by the command.
 */
final class ImportReport
{
    /** @var array<string, int> */
    private array $created = [];

    /** @var array<string, int> */
    private array $replaced = [];

    /** @var array<string, int> */
    private array $skipped = [];

    /** @var list<string> */
    private array $warnings = [];

    public function created(string $kind, int $count = 1): void
    {
        $this->created[$kind] = ($this->created[$kind] ?? 0) + $count;
    }

    public function replaced(string $kind, int $count = 1): void
    {
        $this->replaced[$kind] = ($this->replaced[$kind] ?? 0) + $count;
    }

    public function skipped(string $kind, int $count = 1): void
    {
        $this->skipped[$kind] = ($this->skipped[$kind] ?? 0) + $count;
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * @return array<string, int>
     */
    public function createdCounts(): array
    {
        return $this->created;
    }

    /**
     * @return array<string, int>
     */
    public function replacedCounts(): array
    {
        return $this->replaced;
    }

    /**
     * @return array<string, int>
     */
    public function skippedCounts(): array
    {
        return $this->skipped;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function hasWarnings(): bool
    {
        return [] !== $this->warnings;
    }

    /**
     * One line per kind, in the order things were imported.
     *
     * @return list<string>
     */
    public function summary(): array
    {
        $lines = [];

        foreach (array_keys($this->created + $this->replaced + $this->skipped) as $kind) {
            $lines[] = \sprintf(
                '%s: %d created, %d replaced, %d left alone',
                $kind,
                $this->created[$kind] ?? 0,
                $this->replaced[$kind] ?? 0,
                $this->skipped[$kind] ?? 0,
            );
        }

        return $lines;
    }
}
