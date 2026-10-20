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

namespace TheliaCMS\Tests\Integration\Content\Theme;

use Symfony\UX\TwigComponent\ComponentRendererInterface;
use TheliaCMS\Content\PropType;
use TheliaCMS\Content\SectionCatalog;
use TheliaCMS\Content\Theme\ComponentPropsReader;
use TheliaCMS\Content\Theme\ThemeComponentSection;
use TheliaCMS\Content\Theme\ThemeComponentSections;
use TheliaCMS\Content\Theme\ThemeSections;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * A component of the Flexy theme, read off its template and rendered as a
 * section: the markup is the theme's, so its stylesheet already dresses it.
 */
final class ThemeComponentSectionTest extends CmsIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (null === $this->getService(ComponentPropsReader::class)->templateOf('Molecules:Reinsurance:Base')) {
            self::markTestSkipped('The Flexy theme is not installed: no component to read.');
        }
    }

    public function testThePropsOfAComponentAreReadOffItsTemplate(): void
    {
        $read = $this->getService(ComponentPropsReader::class)->read('Organisms:ArticleCard:Base');
        $props = [];

        foreach ($read['schema']->props as $prop) {
            $props[$prop->name] = $prop;
        }

        self::assertSame(['title', 'date', 'href', 'description', 'source', 'id'], array_keys($props));
        self::assertSame(PropType::Text, $props['title']->type);
        self::assertFalse($props['title']->required, 'A prop with a default is not required.');
        self::assertSame('', $props['title']->default);
        self::assertStringContainsString('Optional secondary text', $props['date']->help ?? '');
        self::assertSame('#', $props['href']->default);
        self::assertFalse($props['description']->required, 'Documented as nullable.');
    }

    public function testAnUndocumentedComponentStillReadsItsDeclaredProps(): void
    {
        $read = $this->getService(ComponentPropsReader::class)->read('Molecules:Button:Base');
        $names = array_map(static fn ($prop) => $prop->name, $read['schema']->props);

        self::assertContains('icon_left', $names, 'camelCase in Twig, snake_case in a document.');
        self::assertContains('fill', $names);
    }

    public function testAComponentTheThemeDoesNotShipIsAnError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no component "Nope:Missing:Base"');

        $this->getService(ComponentPropsReader::class)->read('Nope:Missing:Base');
    }

    public function testAComponentRendersThroughTheComponentRendererWithItsOwnMarkup(): void
    {
        $this->useAnInstalledFrontTheme();
        $read = $this->getService(ComponentPropsReader::class)->read('Molecules:Reinsurance:Base');
        $section = new ThemeComponentSection('Molecules:Reinsurance:Base', $read['description'], $read['schema'], ['items' => 'items'], $this->getService(ComponentRendererInterface::class));

        $html = $section->render($section->normalizeProps([
            'items' => [['icon' => 'reinsurance-delivery', 'strongText' => 'Free delivery', 'text' => 'from 50€']],
        ]), $this->locale());

        self::assertStringContainsString('class="Reinsurance', $html);
        self::assertStringContainsString('<strong>Free delivery</strong>', $html);
        self::assertSame('Reinsurance Base', $section->label());
    }

    public function testNothingIsOpenedUntilTheThemeDeclaresIt(): void
    {
        $directory = sys_get_temp_dir().'/theliacms-theme-'.bin2hex(random_bytes(4));
        mkdir($directory.'/config', 0o777, true);

        try {
            self::assertSame([], ThemeSections::declaredIn([$directory])->components, 'No file, nothing opened.');

            file_put_contents($directory.'/config/theliacms.yaml', "sections:\n    components:\n        - Molecules:Reinsurance:Base\n        - Organisms:ArticleCard:Base\n");
            self::assertSame(['Molecules:Reinsurance:Base', 'Organisms:ArticleCard:Base'], ThemeSections::declaredIn([$directory])->components);

            $child = $directory.'/child';
            mkdir($child);
            self::assertSame(['Molecules:Reinsurance:Base', 'Organisms:ArticleCard:Base'], ThemeSections::declaredIn([$child, $directory])->components, 'A child without a declaration inherits its parent.');

            file_put_contents($directory.'/config/theliacms.yaml', "sections:\n    components:\n        - not a component\n");
            $this->expectException(\InvalidArgumentException::class);
            ThemeSections::declaredIn([$directory]);
        } finally {
            @unlink($directory.'/config/theliacms.yaml');
            @rmdir($directory.'/child');
            @rmdir($directory.'/config');
            @rmdir($directory);
        }
    }

    public function testTheProviderFollowsTheDeclarationOfTheActiveTheme(): void
    {
        // Whatever the theme declares — nothing, or its own components — is
        // what the provider opens, one section per component that reads,
        // and what it puts away is left out of the catalogue.
        $provider = $this->getService(ThemeComponentSections::class);
        $declaration = $provider->declaration();
        $opened = array_map(static fn ($section): string => $section->name(), iterator_to_array($provider->sections(), false));

        self::assertSame($declaration->components, $opened, 'Every declared component is a section, in order.');

        $names = $this->getService(SectionCatalog::class)->names();

        foreach ($declaration->components as $component) {
            self::assertContains($component, $names);
        }

        foreach ($declaration->hidden as $hidden) {
            self::assertNotContains($hidden, $names, 'A section the theme puts away is not offered.');
        }
    }
}
