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

namespace TheliaCMS\Tests\Integration\Builder;

use Symfony\Component\Asset\Packages;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;
use TheliaCMS\Builder\ActiveThemeCanvas;
use TheliaCMS\Builder\CmsBuilderConfig;
use TheliaCMS\Builder\ThemeCanvas;
use TheliaCMS\Front\BlockStyles;
use TheliaCMS\Partial\PartialRegistry;
use TheliaCMS\Settings\CmsSettings;
use TheliaCMS\Settings\SiteStyles;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\TheliaCMS;

/**
 * The canvas of the editor as a theme declares it, read through the options the
 * builder really hands the page builder bundle.
 *
 * The themes are written to a temporary directory, a child and its parent,
 * and the builder is built on the services of the shop with that child as the
 * active theme: the asset mapper, the asset packages and the routes are the
 * real ones.
 */
final class ThemeCanvasDeclarationTest extends CmsIntegrationTestCase
{
    private const string FONT_URL = 'https://fonts.example.com/css2?family=Brand';

    private string $root;

    private ?string $previousStylesheet = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/theliacms-canvas-themes-'.bin2hex(random_bytes(6));
        $this->previousStylesheet = TheliaCMS::getConfigValue('builder_stylesheet');
        TheliaCMS::setConfigValue('builder_stylesheet', '');
    }

    protected function tearDown(): void
    {
        TheliaCMS::setConfigValue('builder_stylesheet', $this->previousStylesheet ?? '');

        foreach (['child', 'parent'] as $theme) {
            @unlink($this->root.'/'.$theme.'/template.xml');
            @unlink($this->root.'/'.$theme.'/'.ThemeCanvas::FILE_NAME);
            @rmdir($this->root.'/'.$theme.'/config');
            @rmdir($this->root.'/'.$theme);
        }

        @rmdir($this->root);

        parent::tearDown();
    }

    public function testWithoutDeclarationTheCanvasLoadsWhatItLoadedBefore(): void
    {
        $builder = $this->getService(CmsBuilderConfig::class);
        $default = $this->getService(AssetMapperInterface::class)->getAsset('styles/app.css')?->publicPath;

        self::assertNotNull($default, 'The theme of the shop has no compiled styles/app.css to compare with.');

        $options = $builder->editorOptions();

        self::assertContains($default, $options['canvas']['styles']);
        self::assertArrayNotHasKey('frameContent', $options['canvas'], 'GrapesJS writes its own document.');
        self::assertSame('cms-page-content', $options['wrapperClasses']);
        self::assertSame($default, $builder->getConfig()['appStylesheet'] ?? null);
        self::assertSame([], $builder->canvasWrapperClasses());
    }

    public function testDeclaredStylesheetsReplaceTheDefaultOne(): void
    {
        $logicalPath = $this->anotherStylesheetOfTheTheme();
        $this->writeThemes(child: <<<YAML
            canvas:
                stylesheets:
                    - {$logicalPath}
                    - https://fonts.example.com/css2?family=Brand
            YAML);

        $builder = $this->builderOnTheChildTheme();
        $styles = $builder->editorOptions()['canvas']['styles'];
        $resolved = $this->getService(AssetMapperInterface::class)->getAsset($logicalPath)?->publicPath;

        self::assertNotNull($resolved);
        self::assertMatchesRegularExpression('#-[\w-]{7,}\.css$#', $resolved, 'The name of the last build, not the logical path.');
        $position = array_search($resolved, $styles, true);

        self::assertIsInt($position, 'The declared logical path is not loaded.');
        self::assertSame([$resolved, self::FONT_URL], \array_slice($styles, $position, 2), 'The declared sheets, in their order.');
        self::assertNotContains(
            $this->getService(AssetMapperInterface::class)->getAsset('styles/app.css')?->publicPath,
            $styles,
            'The default sheet is replaced, not added to.',
        );
        self::assertStringContainsString('/cms/blocks.css', $styles[0], 'The socle of the block catalogue still comes first.');
        self::assertNull($builder->getConfig()['appStylesheet'] ?? null, 'Nothing is loaded ahead of the declared sheets.');
    }

    public function testTheRootOfTheCanvasCarriesTheDeclaredClasses(): void
    {
        $this->writeThemes(child: <<<'YAML'
            canvas:
                html_class: brand
                body_class: page
                wrapper_class: page__content rich-text
            YAML);

        $builder = $this->builderOnTheChildTheme();
        $options = $builder->editorOptions();

        self::assertSame(
            '<!DOCTYPE html><html class="brand"><head></head><body class="page"></body></html>',
            $options['canvas']['frameContent'] ?? null,
        );
        self::assertSame(['page__content', 'rich-text'], $builder->canvasWrapperClasses());
        self::assertSame('cms-page-content', $options['wrapperClasses'], 'The saved wrapper class stays the one every page is published with.');
    }

    public function testAChildThemeInheritsTheDeclarationOfItsParent(): void
    {
        $this->writeThemes(
            child: "canvas:\n    html_class: brand\n",
            parent: "canvas:\n    stylesheets: [".self::FONT_URL."]\n",
        );

        $builder = $this->builderOnTheChildTheme();
        $options = $builder->editorOptions();

        self::assertContains(self::FONT_URL, $options['canvas']['styles']);
        self::assertStringContainsString('class="brand"', $options['canvas']['frameContent'] ?? '');
    }

    public function testTheStylesheetSetForTheSiteStillWins(): void
    {
        $this->writeThemes(child: "canvas:\n    stylesheets: [".self::FONT_URL."]\n");
        TheliaCMS::setConfigValue('builder_stylesheet', '/site/canvas.css');

        $builder = $this->builderOnTheChildTheme();

        self::assertContains('/site/canvas.css', $builder->editorOptions()['canvas']['styles']);
        self::assertNotContains(self::FONT_URL, $builder->editorOptions()['canvas']['styles']);
        self::assertSame('/site/canvas.css', $builder->getConfig()['appStylesheet'] ?? null);
    }

    /**
     * A stylesheet the asset mapper of the shop knows, other than the default.
     *
     * Looked up rather than named: which sheets a theme ships besides
     * `styles/app.css` is the theme's business, and a name copied from one
     * theme fails on every shop running another.
     */
    private function anotherStylesheetOfTheTheme(): string
    {
        foreach ($this->getService(AssetMapperInterface::class)->allAssets() as $asset) {
            if (str_starts_with($asset->logicalPath, 'styles/') && str_ends_with($asset->logicalPath, '.css') && 'styles/app.css' !== $asset->logicalPath) {
                return $asset->logicalPath;
            }
        }

        self::markTestSkipped('The theme of the shop ships no stylesheet besides styles/app.css.');
    }

    private function writeThemes(string $child, ?string $parent = null): void
    {
        foreach (['child' => $child, 'parent' => $parent] as $theme => $declaration) {
            mkdir($this->root.'/'.$theme.'/config', 0o777, true);

            if (null !== $declaration) {
                file_put_contents($this->root.'/'.$theme.'/'.ThemeCanvas::FILE_NAME, $declaration);
            }
        }

        file_put_contents($this->root.'/child/template.xml', $this->descriptor($this->themeName('parent')));
        file_put_contents($this->root.'/parent/template.xml', $this->descriptor(null));
    }

    /**
     * A builder on the services of the shop, with the child theme active.
     */
    private function builderOnTheChildTheme(): CmsBuilderConfig
    {
        $child = new TemplateDefinition($this->themeName('child'), TemplateDefinition::FRONT_OFFICE);

        self::assertCount(1, $child->getParentList() ?? [], 'The child theme does not see its parent.');

        $templates = $this->createStub(TemplateHelperInterface::class);
        $templates->method('getActiveFrontTemplate')->willReturn($child);

        return new CmsBuilderConfig(
            $this->getService(AssetMapperInterface::class),
            $this->getService(UrlGeneratorInterface::class),
            $this->getService(PartialRegistry::class),
            $this->getService(TranslatorInterface::class),
            $this->getService(SiteStyles::class),
            $this->getService(BlockStyles::class),
            new ActiveThemeCanvas($templates),
            $this->getService(Packages::class),
            $this->getService(CmsSettings::class),
        );
    }

    /**
     * The name Thelia resolves a front theme by is its directory under
     * `templates/frontOffice/`; a relative one reaches the temporary directory.
     */
    private function themeName(string $theme): string
    {
        $from = explode('/', trim(THELIA_TEMPLATE_DIR.TemplateDefinition::FRONT_OFFICE_SUBDIR, '/'));
        $to = explode('/', trim($this->root.'/'.$theme, '/'));

        while ([] !== $from && [] !== $to && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return str_repeat('../', \count($from)).implode('/', $to);
    }

    private function descriptor(?string $parent): string
    {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <template xmlns="http://thelia.net/schema/dic/template"
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xsi:schemaLocation="http://thelia.net/schema/dic/template http://thelia.net/schema/dic/template/template-1_0.xsd">
                <descriptive locale="en"><title>Canvas test theme</title></descriptive>
                {$this->parentElement($parent)}
                <languages><language>en_US</language></languages>
                <version>1.0.0</version>
                <authors><author><name>Thelia</name><company>Thelia</company><email>contact@thelia.net</email><website>thelia.net</website></author></authors>
                <thelia>3.0.0</thelia>
                <stability>other</stability>
            </template>
            XML;
    }

    private function parentElement(?string $parent): string
    {
        return null === $parent ? '' : '<parent>'.$parent.'</parent>';
    }
}
