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

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The font files a site uploaded to write with.
 *
 * Only `.woff2` is taken: it is the one format every current browser reads,
 * and one format means one file per face instead of four. The files live under
 * `local/media`, like the store images, and are served by a route of the
 * module — nothing is written into the public directory.
 */
final readonly class SiteFonts
{
    private const string FORMAT = 'woff2';

    /** WOFF2 files open with the signature `wOF2`. */
    private const string SIGNATURE = 'wOF2';

    public function __construct(
        private string $directory = THELIA_LOCAL_DIR.'media'.\DIRECTORY_SEPARATOR.'cms-fonts',
    ) {
    }

    /**
     * @return list<string> file names, sorted
     */
    public function all(): array
    {
        $files = glob($this->directory.\DIRECTORY_SEPARATOR.'*.'.self::FORMAT) ?: [];

        return array_values(array_map(basename(...), $files));
    }

    public function has(string $file): bool
    {
        return null !== $this->path($file);
    }

    /**
     * The file on disk, or null when the name is not one of ours.
     */
    public function path(string $file): ?string
    {
        // A name from a request or a configuration row: one file in this
        // directory, never a path, never another format.
        if ($file !== basename($file) || !str_ends_with(strtolower($file), '.'.self::FORMAT)) {
            return null;
        }

        $path = $this->directory.\DIRECTORY_SEPARATOR.$file;

        return is_file($path) ? $path : null;
    }

    /**
     * Stores an uploaded file under a name safe to write into CSS and URLs.
     *
     * @return string the stored file name
     *
     * @throws \RuntimeException when the file is not what a .woff2 says it is
     */
    public function store(UploadedFile $file): string
    {
        $handle = fopen($file->getPathname(), 'rb');
        $opening = false !== $handle ? (string) fread($handle, 4) : '';

        if (false !== $handle) {
            fclose($handle);
        }

        if (self::SIGNATURE !== $opening) {
            throw new \RuntimeException('This file is not a WOFF2 font.');
        }

        $name = $this->nameFor($file->getClientOriginalName());

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }

        $file->move($this->directory, $name);

        return $name;
    }

    public function delete(string $file): void
    {
        $path = $this->path($file);

        if (null !== $path) {
            unlink($path);
        }
    }

    /**
     * The uploaded name reduced to letters, digits and dashes — it becomes a
     * font-family, a URL and a file on disk, and has to be at home in all
     * three. A name already taken gets a numbered suffix rather than
     * overwriting the font a page may already use.
     */
    private function nameFor(string $originalName): string
    {
        $base = pathinfo($originalName, \PATHINFO_FILENAME);
        $base = (string) preg_replace('/[^a-zA-Z0-9-]+/', '-', $base);
        $base = trim($base, '-') ?: 'font';

        $name = $base.'.'.self::FORMAT;

        for ($suffix = 2; $this->has($name); $suffix++) {
            $name = $base.'-'.$suffix.'.'.self::FORMAT;
        }

        return $name;
    }
}
