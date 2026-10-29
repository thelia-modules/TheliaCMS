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
            file_put_contents($directory.'/config/theliacms.yaml', "sections:\n    components:\n        - Layouts:CrossSelling:Base\n    hidden:\n        - Partial:latest-contents\n        - Partial:cms-menu\n");
            $declared = ThemeSections::declaredIn([$directory]);

            self::assertSame(['Layouts:CrossSelling:Base'], $declared->components);
            self::assertSame(['Partial:latest-contents', 'Partial:cms-menu'], $declared->hidden);

            file_put_contents($directory.'/config/theliacms.yaml', "sections:\n    components: []\n    hidden: not a list\n");
            $this->expectException(\InvalidArgumentException::class);
            ThemeSections::declaredIn([$directory]);
        } finally {
            @unlink($directory.'/config/theliacms.yaml');
            @rmdir($directory.'/config');
            @rmdir($directory);
        }
    }

    public function testATHemeGivesAComponentTheExampleItIsShownWith(): void
    {
        $directory = sys_get_temp_dir().'/theliacms-theme-'.bin2hex(random_bytes(4));
        mkdir($directory.'/config', 0o777, true);

        try {
            file_put_contents($directory.'/config/theliacms.yaml', "sections:\n    components:\n        - Organisms:VideoPlayer:Base\n    examples:\n        Organisms:VideoPlayer:Base:\n            provider: youtube\n            embedUrl: https://www.youtube-nocookie.com/embed/abc\n");
            $declared = ThemeSections::declaredIn([$directory]);

            self::assertSame(['Organisms:VideoPlayer:Base' => ['provider' => 'youtube', 'embedUrl' => 'https://www.youtube-nocookie.com/embed/abc']], $declared->examples);

            file_put_contents($directory.'/config/theliacms.yaml', "sections:\n    components: []\n    examples:\n        - Organisms:VideoPlayer:Base\n");
            $this->expectException(\InvalidArgumentException::class);
            ThemeSections::declaredIn([$directory]);
        } finally {
            @unlink($directory.'/config/theliacms.yaml');
            @rmdir($directory.'/config');
            @rmdir($directory);
        }
    }

    public function testATHemeNamesTheClassesItStylesWrittenTextWith(): void
    {
        $directory = sys_get_temp_dir().'/theliacms-theme-'.bin2hex(random_bytes(4));
        mkdir($directory.'/config', 0o777, true);

        try {
            file_put_contents($directory.'/config/theliacms.yaml', "sections:\n    components: []\n");
            self::assertNull(ThemeSections::declaredIn([$directory])->richTextClass, 'A theme that names none adds none.');

            file_put_contents($directory.'/config/theliacms.yaml', "sections:\n    richtext_class: ' wysiwyg wysiwyg-theme '\n    components: []\n");
            self::assertSame('wysiwyg wysiwyg-theme', ThemeSections::declaredIn([$directory])->richTextClass);

            file_put_contents($directory.'/config/theliacms.yaml', "sections:\n    richtext_class: 'prose\" onload=\"x'\n    components: []\n");
            $this->expectException(\InvalidArgumentException::class);
            ThemeSections::declaredIn([$directory]);
        } finally {
            @unlink($directory.'/config/theliacms.yaml');
            @rmdir($directory.'/config');
            @rmdir($directory);
        }
    }

    public function testATHemeNamesTheStimulusControllersItsComponentsUse(): void
    {
        $directory = sys_get_temp_dir().'/theliacms-theme-'.bin2hex(random_bytes(4));
        mkdir($directory.'/config', 0o777, true);

        try {
            file_put_contents($directory.'/config/theliacms.yaml', "sections:\n    components: []\n");
            self::assertSame([], ThemeSections::declaredIn([$directory])->controllers, 'A theme that names none keeps none.');

            file_put_contents($directory.'/config/theliacms.yaml', "sections:\n    components: []\n    controllers:\n        - Organisms--Carousel--base\n        - tabs\n");
            self::assertSame(['Organisms--Carousel--base', 'tabs'], ThemeSections::declaredIn([$directory])->controllers);

            file_put_contents($directory.'/config/theliacms.yaml', "sections:\n    components: []\n    controllers:\n        - 'tabs\" onclick=\"x'\n");
            $this->expectException(\InvalidArgumentException::class);
            ThemeSections::declaredIn([$directory]);
        } finally {
            @unlink($directory.'/config/theliacms.yaml');
            @rmdir($directory.'/config');
            @rmdir($directory);
        }
    }
}
