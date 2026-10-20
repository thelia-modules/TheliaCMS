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

namespace TheliaCMS\Tests\Unit\Content\Theme;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Content\Theme\ThemeSections;

/**
 * What a theme declares: the components it opens, and the sections of the
 * module it puts away because it opens its own for the same thing.
 */
final class ThemeSectionsTest extends TestCase
{
    public function testATHemePutsSectionsOfTheModuleAway(): void
    {
        $directory = sys_get_temp_dir().'/theliacms-theme-'.bin2hex(random_bytes(4));
        mkdir($directory.'/config', 0o777, true);

        try {
            file_put_contents($directory.'/config/theliacms.yaml', "sections:\n    components:\n        - Layouts:CrossSelling:Base\n    hidden:\n        - Partial:product-strip\n        - Partial:product-card\n");
            $declared = ThemeSections::declaredIn([$directory]);

            self::assertSame(['Layouts:CrossSelling:Base'], $declared->components);
            self::assertSame(['Partial:product-strip', 'Partial:product-card'], $declared->hidden);

            file_put_contents($directory.'/config/theliacms.yaml', "sections:\n    components: []\n    hidden: not a list\n");
            $this->expectException(\InvalidArgumentException::class);
            ThemeSections::declaredIn([$directory]);
        } finally {
            @unlink($directory.'/config/theliacms.yaml');
            @rmdir($directory.'/config');
            @rmdir($directory);
        }
    }
}
