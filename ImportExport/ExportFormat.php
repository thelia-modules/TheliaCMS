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
 * The envelope every export file carries.
 *
 * The version is the version of the file format, not of the module: a file
 * written today must still be readable by a module released in two years, and
 * the only way to promise that is to state which shape the reader is looking at
 * and to refuse the ones it does not know.
 */
final class ExportFormat
{
    public const string NAME = 'thelia-cms';

    public const int VERSION = 1;

    /**
     * Sections a reader knows. Anything else in the file is ignored rather than
     * rejected, so a file written by a later module still imports what this one
     * understands.
     *
     * @var list<string>
     */
    public const array SECTIONS = ['pages', 'blocks', 'menus', 'forms', 'settings', 'media'];

    /**
     * @param list<string> $locales
     *
     * @return array<string, mixed>
     */
    public static function header(string $moduleVersion, array $locales, \DateTimeImmutable $generatedAt): array
    {
        return [
            'format' => self::NAME,
            'version' => self::VERSION,
            'generated_at' => $generatedAt->format(\DATE_ATOM),
            'module_version' => $moduleVersion,
            'locales' => array_values($locales),
        ];
    }

    /**
     * @param array<mixed> $document
     *
     * @throws \InvalidArgumentException when the file is not one this module can read
     */
    public static function assertReadable(array $document): void
    {
        $format = $document['format'] ?? null;

        if (self::NAME !== $format) {
            throw new \InvalidArgumentException(\sprintf('This file is not a Thelia CMS export: it declares the format "%s".', \is_string($format) ? $format : \gettype($format)));
        }

        $version = $document['version'] ?? null;

        if (!\is_int($version)) {
            throw new \InvalidArgumentException('This export file carries no format version.');
        }

        if ($version > self::VERSION) {
            throw new \InvalidArgumentException(\sprintf('This file is in format version %d, and this module reads up to version %d. Update the module first.', $version, self::VERSION));
        }
    }
}
