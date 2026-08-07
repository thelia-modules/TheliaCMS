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

use Thelia\Model\ConfigQuery;

/**
 * The icon of the site, as uploaded in the store configuration.
 *
 * Thelia keeps it under `local/media/images/store/`, which is not served to the
 * public, and the back office reads it through a route only an administrator
 * may open. A theme that wants to show the icon a client uploaded therefore has
 * no address to point at, and every theme ends up shipping a favicon of its own
 * that nobody can change without a developer.
 */
final readonly class SiteIcon
{
    /** Configuration key the store form writes the uploaded file name to. */
    private const string CONFIG_KEY = 'favicon_file';

    /**
     * Extensions a browser accepts as an icon. An uploaded file with anything
     * else is ignored rather than served: this path answers before any
     * authentication, and it must not become a way to publish a file.
     *
     * @var array<string, string>
     */
    private const array TYPES = [
        'ico' => 'image/x-icon',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'gif' => 'image/gif',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    /**
     * The file on disk, or null when nothing usable is configured.
     */
    public function path(): ?string
    {
        $fileName = ConfigQuery::read(self::CONFIG_KEY);

        if (!\is_string($fileName) || '' === trim($fileName)) {
            return null;
        }

        // The name comes from a configuration row, which an administrator can
        // write by hand. It names a file in one directory, never a path.
        $fileName = basename($fileName);

        if (null === $this->mimeTypeOf($fileName)) {
            return null;
        }

        $path = $this->directory().\DIRECTORY_SEPARATOR.$fileName;

        return is_file($path) ? $path : null;
    }

    public function mimeTypeOf(string $fileName): ?string
    {
        return self::TYPES[strtolower(pathinfo($fileName, \PATHINFO_EXTENSION))] ?? null;
    }

    public function exists(): bool
    {
        return null !== $this->path();
    }

    private function directory(): string
    {
        $configured = ConfigQuery::read('images_library_path');

        $base = \is_string($configured) && '' !== $configured
            ? THELIA_ROOT.$configured
            : THELIA_LOCAL_DIR.'media'.\DIRECTORY_SEPARATOR.'images';

        return $base.\DIRECTORY_SEPARATOR.'store';
    }
}
