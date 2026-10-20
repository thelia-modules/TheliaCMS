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

namespace TheliaCMS\Content;

/**
 * The content of a page or of a block in one language: an ordered list of
 * sections, each a component of the catalogue with its props.
 *
 * This is what is stored, what is rendered to HTML and what a later edit
 * changes. The HTML next to it in the database is only a rendering of it. The
 * format carries a version so that a document written today is still read by
 * a module released later.
 */
final readonly class Document
{
    public const int VERSION = 1;

    /**
     * @param list<Section> $sections
     */
    public function __construct(
        public array $sections,
    ) {
    }

    /**
     * A document made of a single piece of free markup: what a seeded page, a
     * test fixture or a hand-written page holds.
     */
    public static function richText(string $html, string $sectionId = 'content'): self
    {
        return new self([new Section($sectionId, RichTextSection::NAME, ['html' => $html])]);
    }

    public function isEmpty(): bool
    {
        return [] === $this->sections;
    }

    public function section(string $id): ?Section
    {
        foreach ($this->sections as $section) {
            if ($section->id === $id) {
                return $section;
            }
        }

        return null;
    }

    public function withSection(Section $replacement): self
    {
        $sections = [];

        foreach ($this->sections as $section) {
            $sections[] = $section->id === $replacement->id ? $replacement : $section;
        }

        return new self($sections);
    }

    /**
     * @return array{version: int, sections: list<array{id: string, component: string, props: array<string, mixed>}>}
     */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'sections' => array_map(static fn (Section $section): array => $section->toArray(), $this->sections),
        ];
    }

    public function toJson(): string
    {
        // Slashes and non-ASCII characters are kept as they are: the stored
        // document is searched for image addresses (`/image-library/{id}/`)
        // and block references, and both are written the way the HTML spells
        // them.
        return json_encode($this->toArray(), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    /**
     * @throws InvalidDocumentException when the JSON is not a document
     */
    public static function fromJson(?string $json): ?self
    {
        if (null === $json || '' === trim($json)) {
            return null;
        }

        try {
            $decoded = json_decode($json, true, 64, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw InvalidDocumentException::because(['The document is not valid JSON: '.$exception->getMessage()]);
        }

        if (!\is_array($decoded)) {
            throw InvalidDocumentException::because(['The document is not a JSON object.']);
        }

        return self::fromArray($decoded);
    }

    /**
     * Reads the shape only: whether each component exists and whether its
     * props make sense is the job of the validator, which knows the catalogue.
     *
     * @param array<mixed> $document
     *
     * @throws InvalidDocumentException when the array is not shaped like a document
     */
    public static function fromArray(array $document): self
    {
        $version = $document['version'] ?? null;

        if (!\is_int($version)) {
            throw InvalidDocumentException::because(['The document carries no format version.']);
        }

        if ($version > self::VERSION) {
            throw InvalidDocumentException::because([\sprintf('The document is in format version %d, and this module reads up to version %d.', $version, self::VERSION)]);
        }

        $sections = $document['sections'] ?? null;

        if (!\is_array($sections) || !array_is_list($sections)) {
            throw InvalidDocumentException::because(['The document holds no list of sections.']);
        }

        $problems = [];
        $read = [];

        foreach ($sections as $index => $section) {
            if (!\is_array($section)) {
                $problems[] = \sprintf('Section %d is not an object.', $index + 1);

                continue;
            }

            $id = $section['id'] ?? null;
            $component = $section['component'] ?? null;
            $props = $section['props'] ?? [];

            if (!\is_string($id) || '' === $id) {
                $problems[] = \sprintf('Section %d has no id.', $index + 1);
            }

            if (!\is_string($component) || '' === $component) {
                $problems[] = \sprintf('Section %d names no component.', $index + 1);
            }

            if (!\is_array($props) || array_is_list($props) && [] !== $props) {
                $problems[] = \sprintf('Section %d has props that are not an object.', $index + 1);
            }

            if (\is_string($id) && '' !== $id && \is_string($component) && '' !== $component && \is_array($props)) {
                $read[] = new Section($id, $component, $props);
            }
        }

        if ([] !== $problems) {
            throw InvalidDocumentException::because($problems);
        }

        return new self($read);
    }
}
