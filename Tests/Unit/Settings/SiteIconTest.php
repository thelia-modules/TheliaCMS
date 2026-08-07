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

namespace TheliaCMS\Tests\Unit\Settings;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheliaCMS\Settings\SiteIcon;

/**
 * The icon is served on a public path, so what counts as an icon is decided
 * here rather than by whatever an administrator typed into a configuration row.
 */
final class SiteIconTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string|null}>
     */
    public static function fileNames(): iterable
    {
        yield 'ico' => ['favicon.ico', 'image/x-icon'];
        yield 'png' => ['icon.png', 'image/png'];
        yield 'svg' => ['icon.svg', 'image/svg+xml'];
        yield 'webp' => ['icon.webp', 'image/webp'];
        yield 'upper case extension' => ['ICON.PNG', 'image/png'];
        yield 'anything else' => ['config.php', null];
        yield 'no extension' => ['icon', null];
        yield 'double extension' => ['icon.png.php', null];
    }

    #[DataProvider('fileNames')]
    public function testOnlyImageTypesAreServed(string $fileName, ?string $expected): void
    {
        self::assertSame($expected, (new SiteIcon())->mimeTypeOf($fileName));
    }
}
