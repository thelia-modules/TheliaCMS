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

namespace TheliaCMS\Settings;

/**
 * The blocks an administrator took out of the editor panel.
 *
 * Kept as the list of what is off rather than of what is on: a block a module
 * adds later is offered until somebody switches it off, and a module that
 * leaves takes its blocks away without a stale "on" staying behind. The ids
 * are the ones the catalogue and the partial registry name their blocks by
 * (`cms-hero`, `cms-menu`...), so one list covers both kinds.
 *
 * Switching a block off changes what the panel offers, nothing else: a page
 * that already holds the block keeps it, in the editor as on the front.
 */
final readonly class DisabledBlocks
{
    private const string SEPARATOR = ',';

    /** @var list<string> */
    private array $ids;

    /**
     * @param iterable<mixed> $ids anything but a non-empty string is dropped
     */
    public function __construct(iterable $ids = [])
    {
        $kept = [];

        foreach ($ids as $id) {
            if (!\is_string($id) || '' === trim($id)) {
                continue;
            }

            $kept[] = trim($id);
        }

        $this->ids = array_values(array_unique($kept));
    }

    public static function none(): self
    {
        return new self();
    }

    public static function fromStorage(?string $raw): self
    {
        return new self(explode(self::SEPARATOR, (string) $raw));
    }

    public function toStorage(): string
    {
        return implode(self::SEPARATOR, $this->ids);
    }

    public function contains(string $id): bool
    {
        return \in_array($id, $this->ids, true);
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return $this->ids;
    }

    public function isEmpty(): bool
    {
        return [] === $this->ids;
    }

    /**
     * The list once the settings screen is saved.
     *
     * Among the blocks the screen offered, those left unticked are off. What
     * the stored list names outside that offer is kept as it was: the block of
     * a module switched off at that moment was not on the screen to be chosen,
     * and saving the settings is not the gesture that turns it back on.
     *
     * @param list<string> $offered the ids the screen listed
     * @param list<string> $enabled the ids left ticked
     */
    public function afterChoosing(array $offered, array $enabled): self
    {
        return new self([
            ...array_diff($this->ids, $offered),
            ...array_diff($offered, $enabled),
        ]);
    }
}
