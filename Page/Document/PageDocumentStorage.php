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

namespace TheliaCMS\Page\Document;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Where the files of the pages are kept, and under which names.
 *
 * A stored name is the slug of the name the file was sent under, a dash,
 * eight random hexadecimal digits and the extension: readable in an address,
 * never guessed from the page, and never the name somebody typed. Every name
 * read from the database or from an address is checked against that shape
 * before it becomes a path, so no stored value can point outside the
 * directory.
 */
final readonly class PageDocumentStorage
{
    /** Characters of the slug kept in a stored name, the suffix aside. */
    public const int SLUG_MAX_LENGTH = 80;

    /** The slug of a name with nothing left to keep, such as "?.pdf". */
    private const string FALLBACK_SLUG = 'document';

    public function __construct(
        // Given by the tests only: the site keeps its files under its local
        // directory, which outlives the deployments.
        private ?string $directory = null,
    ) {
    }

    public function directory(): string
    {
        return $this->directory ?? THELIA_LOCAL_DIR.'media'.\DIRECTORY_SEPARATOR.'documents'.\DIRECTORY_SEPARATOR.'cms-page'.\DIRECTORY_SEPARATOR;
    }

    public static function fileNamePattern(): string
    {
        return '/^[a-z0-9]+(?:-[a-z0-9]+)*-[0-9a-f]{8}\.(?:'.implode('|', PageDocumentFormat::extensions()).')$/D';
    }

    public static function isStoredName(string $file): bool
    {
        return 1 === preg_match(self::fileNamePattern(), $file);
    }

    /**
     * A new stored name for a file sent under this name, with this extension.
     */
    public function nameFor(string $originalName, string $extension): string
    {
        $extension = strtolower($extension);

        if (!\in_array($extension, PageDocumentFormat::extensions(), true)) {
            throw new \InvalidArgumentException(\sprintf('Extension not accepted for a page document: "%s".', $extension));
        }

        $slug = (new AsciiSlugger())->slug(pathinfo($originalName, \PATHINFO_FILENAME))->lower()->toString();
        $slug = trim(substr($slug, 0, self::SLUG_MAX_LENGTH), '-');

        if ('' === $slug) {
            $slug = self::FALLBACK_SLUG;
        }

        $name = $slug.'-'.bin2hex(random_bytes(4)).'.'.$extension;

        // The slugger answers in [a-z0-9-] runs; a name it would write
        // otherwise must not be stored under a shape the readers refuse.
        if (!self::isStoredName($name)) {
            throw new \LogicException(\sprintf('Stored name out of shape: "%s".', $name));
        }

        return $name;
    }

    /**
     * The path of a stored file, whether it exists or not.
     *
     * @throws \InvalidArgumentException when the name is not one this storage writes
     */
    public function path(string $file): string
    {
        if (!self::isStoredName($file)) {
            throw new \InvalidArgumentException(\sprintf('Not the name of a page document: "%s".', $file));
        }

        return $this->directory().$file;
    }

    public function exists(string $file): bool
    {
        return self::isStoredName($file) && is_file($this->directory().$file);
    }

    /**
     * Moves an uploaded file into the storage under its stored name.
     */
    public function store(UploadedFile $file, string $name): void
    {
        $path = $this->path($name);
        $this->ensureDirectory();
        $file->move(\dirname($path), basename($path));
    }

    /**
     * Copies a stored file under a new name, for a copy of its page.
     *
     * @return string the stored name of the copy
     */
    public function copy(string $file): string
    {
        $source = $this->path($file);
        $copy = $this->nameFor(preg_replace('/-[0-9a-f]{8}\.[a-z]+$/', '', $file) ?? $file, PageDocumentFormat::extensionOf($file));
        $this->ensureDirectory();

        if (!is_file($source) || !copy($source, $this->path($copy))) {
            throw new \RuntimeException(\sprintf('Page document not copied: "%s".', $file));
        }

        return $copy;
    }

    /**
     * Removes a stored file; a file already gone is not an error.
     */
    public function delete(string $file): void
    {
        $path = $this->path($file);

        if (is_file($path) && !unlink($path) && is_file($path)) {
            throw new \RuntimeException(\sprintf('Page document not deleted: "%s".', $file));
        }
    }

    private function ensureDirectory(): void
    {
        $directory = $this->directory();

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new \RuntimeException(\sprintf('Directory not created: "%s".', $directory));
        }
    }
}
