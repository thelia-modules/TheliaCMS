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
 * A decoded export file, read as sections rather than as an array of arrays.
 *
 * Everything here is pure: an import that fails on a malformed file has to fail
 * before it has written anything, so the file is understood first and applied
 * second.
 */
final readonly class SiteDocument
{
    /**
     * @param array<string, mixed> $document
     */
    private function __construct(
        private array $document,
    ) {
    }

    /**
     * @throws \InvalidArgumentException on anything this module cannot read
     */
    public static function fromJson(string $json): self
    {
        try {
            $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('This file is not valid JSON: '.$exception->getMessage(), 0, $exception);
        }

        if (!\is_array($decoded)) {
            throw new \InvalidArgumentException('This file does not contain a JSON object.');
        }

        ExportFormat::assertReadable($decoded);

        return new self($decoded);
    }

    /**
     * @param array<string, mixed> $document
     */
    public static function fromArray(array $document): self
    {
        ExportFormat::assertReadable($document);

        return new self($document);
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        return array_values(array_filter(
            array_map(strval(...), $this->section('locales')),
            static fn (string $locale): bool => '' !== $locale,
        ));
    }

    /**
     * The pages, parents before children.
     *
     * A page whose parent is not in the file becomes a root page instead of
     * being dropped: exporting a branch of the tree and importing it elsewhere
     * is a legitimate thing to do, and losing pages silently is not an
     * acceptable answer to it. Whoever imports is told, through the report.
     *
     * @return list<array<string, mixed>>
     */
    public function pages(): array
    {
        $pages = [];

        foreach ($this->section('pages') as $page) {
            if (!\is_array($page) || !isset($page['uid'])) {
                continue;
            }

            $pages[(string) $page['uid']] = $page;
        }

        return $this->parentsFirst($pages);
    }

    /**
     * Pages whose parent is missing from the file, and which are therefore
     * imported at the root of the tree.
     *
     * @return list<string>
     */
    public function reparentedPages(): array
    {
        $known = [];

        foreach ($this->section('pages') as $page) {
            if (\is_array($page) && isset($page['uid'])) {
                $known[(string) $page['uid']] = true;
            }
        }

        $orphans = [];

        foreach ($this->section('pages') as $page) {
            if (!\is_array($page)) {
                continue;
            }

            $parent = $page['parent'] ?? null;

            if (null !== $parent && !isset($known[(string) $parent])) {
                $orphans[] = (string) ($page['uid'] ?? '');
            }
        }

        return $orphans;
    }

    /**
     * The blocks a file written before they were removed carries, read only
     * to say they were left out.
     *
     * @return list<array<string, mixed>>
     */
    public function blocks(): array
    {
        return $this->rowsOf('blocks');
    }

    /**
     * The page types the file describes as templates. Files written before
     * 2.0.0 carry none.
     *
     * @return list<array<string, mixed>>
     */
    public function pageTypes(): array
    {
        return $this->rowsOf('page_types');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function menus(): array
    {
        return $this->rowsOf('menus');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forms(): array
    {
        return $this->rowsOf('forms');
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $settings = $this->document['settings'] ?? [];

        return \is_array($settings) ? $settings : [];
    }

    /**
     * The images the exported content points at, by library id.
     *
     * @return array<int, string> file name, keyed by the id it had on the site that exported
     */
    public function media(): array
    {
        $media = [];

        foreach ($this->section('media') as $entry) {
            if (\is_array($entry) && isset($entry['id'], $entry['file_name'])) {
                $media[(int) $entry['id']] = (string) $entry['file_name'];
            }
        }

        return $media;
    }

    public function generatedAt(): ?string
    {
        $value = $this->document['generated_at'] ?? null;

        return \is_string($value) ? $value : null;
    }

    public function moduleVersion(): ?string
    {
        $value = $this->document['module_version'] ?? null;

        return \is_string($value) ? $value : null;
    }

    /**
     * The version of the file format, checked readable when the document was
     * built.
     */
    public function formatVersion(): int
    {
        return (int) ($this->document['version'] ?? 0);
    }

    /**
     * Whether the contents of pages in this file are documents this
     * module can read, rather than the markup of the former editor.
     */
    public function carriesDocuments(): bool
    {
        return $this->formatVersion() >= ExportFormat::FIRST_VERSION_WITH_DOCUMENTS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rowsOf(string $section): array
    {
        $rows = [];

        foreach ($this->section($section) as $row) {
            if (\is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array<mixed>
     */
    private function section(string $name): array
    {
        $section = $this->document[$name] ?? [];

        return \is_array($section) ? $section : [];
    }

    /**
     * @param array<string, array<string, mixed>> $pages
     *
     * @return list<array<string, mixed>>
     */
    private function parentsFirst(array $pages): array
    {
        $ordered = [];
        $placed = [];
        $remaining = $pages;

        // A page joins the list once its parent has, so a whole branch is
        // created with real parent ids and never with a placeholder.
        while ([] !== $remaining) {
            $progressed = false;

            foreach ($remaining as $uid => $page) {
                $parent = $page['parent'] ?? null;
                $parentUid = null === $parent ? null : (string) $parent;

                if (null !== $parentUid && isset($pages[$parentUid]) && !isset($placed[$parentUid])) {
                    continue;
                }

                $ordered[] = $page;
                $placed[$uid] = true;
                unset($remaining[$uid]);
                $progressed = true;
            }

            // Pages pointing at each other as parents: nothing else can be
            // placed, and they are appended as roots rather than dropped.
            if (!$progressed) {
                foreach ($remaining as $page) {
                    $ordered[] = $page;
                }

                break;
            }
        }

        return $ordered;
    }
}
