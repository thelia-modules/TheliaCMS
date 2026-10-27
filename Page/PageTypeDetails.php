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

namespace TheliaCMS\Page;

use TheliaCMS\Generation\Brief;
use TheliaCMS\Page\Field\FieldDefinitions;

/**
 * A page type as a template of pages (docs/adr/0009): its name, what its
 * pages are about, how they are written, and the fields they carry. A type
 * that is only a code has none of it.
 */
final readonly class PageTypeDetails
{
    /**
     * @param array<string, string> $titles       by locale
     * @param array<string, string> $descriptions by locale
     */
    public function __construct(
        public string $code,
        public array $titles = [],
        public array $descriptions = [],
        public ?string $briefText = null,
        public ?string $briefTone = null,
        public ?string $briefAudience = null,
        public ?FieldDefinitions $fields = null,
        public ?string $instructions = null,
    ) {
    }

    public static function plain(string $code): self
    {
        return new self($code);
    }

    /**
     * The name in this language, else the code: a type is never shown
     * without a name.
     */
    public function title(string $locale): string
    {
        return $this->titles[$locale] ?? $this->code;
    }

    public function description(string $locale): ?string
    {
        return $this->descriptions[$locale] ?? null;
    }

    public function brief(): ?Brief
    {
        if (null === $this->briefText || '' === trim($this->briefText)) {
            return null;
        }

        return new Brief(trim($this->briefText), $this->briefTone, $this->briefAudience);
    }

    /**
     * The rules every page of the type is written by, as markdown; none when
     * the type has no instructions.
     */
    public function instructions(): ?string
    {
        if (null === $this->instructions || '' === trim($this->instructions)) {
            return null;
        }

        return trim($this->instructions);
    }

    public function fields(): FieldDefinitions
    {
        return $this->fields ?? FieldDefinitions::none();
    }

    /**
     * Whether the type is more than a code: a brief, instructions or fields.
     */
    public function isTemplate(): bool
    {
        return null !== $this->brief() || null !== $this->instructions() || !$this->fields()->isEmpty();
    }
}
