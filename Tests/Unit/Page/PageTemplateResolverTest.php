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

namespace TheliaCMS\Tests\Unit\Page;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;
use TheliaCMS\Page\PageTemplateResolver;
use TheliaCMS\Page\PageTemplateSource;

/**
 * The template of a page type: the theme first, the module next, and the usual
 * page template of each of them when neither has one for the type.
 */
final class PageTemplateResolverTest extends TestCase
{
    private const string THEME_PATH = '/themes/acme';

    private string $moduleDirectory;

    /** @var list<string> templates the theme parser finds, without extension */
    private array $themeTemplates = ['index'];

    protected function setUp(): void
    {
        $this->moduleDirectory = sys_get_temp_dir().'/theliacms-page-templates-'.bin2hex(random_bytes(4));
        mkdir($this->moduleDirectory);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->moduleDirectory.'/*') ?: []);
        rmdir($this->moduleDirectory);
    }

    public function testTheThemeTemplateOfTheTypeComesFirst(): void
    {
        $this->themeShips('cmspage-recipe', 'cmspage');
        $this->moduleShips('cmspage-recipe', 'cmspage');

        $template = $this->resolver()->resolve('recipe');

        self::assertSame('cmspage-recipe.html.twig', $template->name);
        self::assertSame(PageTemplateSource::Theme, $template->source);
    }

    public function testTheModuleTemplateOfTheTypeComesBeforeTheUsualOneOfTheTheme(): void
    {
        $this->themeShips('cmspage');
        $this->moduleShips('cmspage-recipe', 'cmspage');

        $template = $this->resolver()->resolve('recipe');

        self::assertSame('@TheliaCMSModule/front/cmspage-recipe.html.twig', $template->name);
        self::assertSame(PageTemplateSource::Module, $template->source);
    }

    public function testATypeWithoutTemplateGetsTheUsualTemplateOfTheTheme(): void
    {
        $this->themeShips('cmspage');
        $this->moduleShips('cmspage');

        $template = $this->resolver()->resolve('recipe');

        self::assertSame('cmspage.html.twig', $template->name);
        self::assertSame(PageTemplateSource::ThemeFallback, $template->source);
    }

    public function testWithNothingInTheThemeTheUsualTemplateOfTheModuleIsLeft(): void
    {
        $this->moduleShips('cmspage');

        $template = $this->resolver()->resolve('recipe');

        self::assertSame('@TheliaCMSModule/front/cmspage.html.twig', $template->name);
        self::assertSame(PageTemplateSource::ModuleFallback, $template->source);
    }

    public function testAnInvalidCodeNeverReachesATemplateName(): void
    {
        $this->themeShips('cmspage', 'cmspage-default');

        $template = $this->resolver()->resolve('../secret');

        self::assertSame('cmspage-default.html.twig', $template->name);
    }

    public function testATypeCodeOnlyMatchesItsOwnTemplate(): void
    {
        // `cms-search` is the search results page the module ships: a type
        // called `search` must not land on it.
        $this->themeShips('cmspage', 'cms-search');
        $this->moduleShips('cmspage', 'cms-search');

        self::assertSame('cmspage.html.twig', $this->resolver()->resolve('search')->name);
    }

    public function testAThemeNoParserDrivesLeavesTheModuleTemplates(): void
    {
        $this->themeTemplates = [];
        $this->moduleShips('cmspage');

        self::assertSame(PageTemplateSource::ModuleFallback, $this->resolver()->resolve('recipe')->source);
    }

    private function themeShips(string ...$templates): void
    {
        $this->themeTemplates = [...$this->themeTemplates, ...$templates];
    }

    private function moduleShips(string ...$templates): void
    {
        foreach ($templates as $template) {
            touch($this->moduleDirectory.'/'.$template.'.html.twig');
        }
    }

    private function resolver(): PageTemplateResolver
    {
        $parser = $this->createStub(ParserInterface::class);
        $parser->method('getFileExtension')->willReturn('html.twig');
        $parser->method('supportTemplateRender')->willReturnCallback(
            fn (string $path, ?string $name): bool => self::THEME_PATH === $path && \in_array($name, $this->themeTemplates, true),
        );

        $theme = $this->createStub(TemplateDefinition::class);
        $theme->method('getAbsolutePath')->willReturn(self::THEME_PATH);

        $templateHelper = $this->createStub(TemplateHelperInterface::class);
        $templateHelper->method('getActiveFrontTemplate')->willReturn($theme);

        return new PageTemplateResolver(
            new ParserResolver([$parser], [], new RequestStack(), $templateHelper),
            $templateHelper,
            $this->moduleDirectory,
        );
    }
}
