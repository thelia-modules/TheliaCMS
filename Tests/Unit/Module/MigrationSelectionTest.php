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

namespace TheliaCMS\Tests\Unit\Module;

use PHPUnit\Framework\TestCase;
use TheliaCMS\TheliaCMS;

/**
 * Which migration files a site gets when it updates the module.
 *
 * A site that skips two releases has to receive both of them, in the order they
 * were written, and a site already up to date has to receive none: replaying a
 * migration is safe, skipping one leaves a table that no code ever creates
 * again.
 */
final class MigrationSelectionTest extends TestCase
{
    public function testASiteUpdatingOneVersionGetsThatVersionOnly(): void
    {
        self::assertSame(['0.6.0'], $this->versionsBetween('0.5.0', '0.6.0'));
    }

    public function testASiteThatSkippedReleasesGetsThemAllInOrder(): void
    {
        $versions = $this->versionsBetween('0.2.0', '0.6.0');

        self::assertSame(['0.3.0', '0.4.0', '0.5.0', '0.6.0'], $versions);
    }

    public function testASiteAlreadyUpToDateGetsNothing(): void
    {
        $current = $this->versionsBetween('0.0.0', '99.0.0');
        $latest = end($current);

        self::assertNotFalse($latest);
        self::assertSame([], $this->versionsBetween($latest, $latest));
    }

    public function testEveryShippedFileIsNamedAfterAVersion(): void
    {
        $versions = $this->versionsBetween('0.0.0', '99.0.0');

        self::assertNotEmpty($versions, 'The module ships migration files.');

        foreach ($versions as $version) {
            // A file named otherwise is never compared the way it reads, so it
            // lands anywhere in the order and applies to anyone. Pre-release
            // suffixes are part of the format: the module ships them, and a
            // migration written for one has to be named after it or no site
            // running that version ever receives it.
            self::assertMatchesRegularExpression('#^\d+\.\d+\.\d+(-[a-z]+\.\d+)?$#', $version);
        }
    }

    /**
     * The names are sorted the way the versions rank, pre-releases included.
     *
     * `version_compare` is what orders the files, and it reads `1.0.0-alpha.2`
     * as coming after `1.0.0-alpha.1` and before `1.0.0`. A plain string sort
     * does not, which is how a column gets altered before it is added.
     */
    public function testTheFilesComeBackInTheOrderTheVersionsRank(): void
    {
        $versions = $this->versionsBetween('0.0.0', '99.0.0');

        $previous = null;

        foreach ($versions as $version) {
            if (null !== $previous) {
                self::assertSame(
                    -1,
                    version_compare($previous, $version),
                    \sprintf('"%s" is shipped before "%s" but does not rank before it.', $previous, $version),
                );
            }

            $previous = $version;
        }
    }

    /**
     * @return list<string>
     */
    private function versionsBetween(string $from, string $to): array
    {
        return array_map(
            static fn (string $file): string => basename($file, '.sql'),
            TheliaCMS::migrationsBetween($from, $to),
        );
    }
}
