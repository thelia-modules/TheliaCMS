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
use Psr\Log\NullLogger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\TwigComponent\ComponentRendererInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;
use TheliaCMS\Content\PropType;
use TheliaCMS\Content\Theme\ComponentPropsReader;
use TheliaCMS\Content\Theme\ThemeComponentSection;
use TheliaCMS\Content\Theme\ThemeComponentSections;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * A component of the theme whose template documents an object, read through
 * the catalogue as the site reads it: the theme writes its example as an
 * object, the section holds one row, the component is handed the row.
 */
final class ThemeObjectPropTest extends TestCase
{
    private const string TEMPLATE = <<<'TWIG'
        {# @prop title string The heading #}
        {# @prop button {href: string, label: string}|null The button under the band #}
        {% props title = '', button = null %}
        <div>{{ title }}{% if button %}<a href="{{ button.href }}">{{ button.label }}</a>{% endif %}</div>
        TWIG;

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/theliacms-object-'.bin2hex(random_bytes(4));
        mkdir($this->directory.'/config', 0o777, true);
        file_put_contents($this->directory.'/config/theliacms.yaml', <<<'YAML'
            sections:
                components:
                    - Layouts:Band:Base
                examples:
                    Layouts:Band:Base:
                        title: New in
                        button:
                            href: /new
                            label: All the novelties
            YAML);
    }

    protected function tearDown(): void
    {
        @unlink($this->directory.'/config/theliacms.yaml');
        @rmdir($this->directory.'/config');
        @rmdir($this->directory);
    }

    public function testTheExampleOfTheThemeWritesTheObjectAndTheComponentGetsIt(): void
    {
        $received = null;
        $sections = iterator_to_array($this->catalogue($received)->sections(), false);

        self::assertCount(1, $sections);
        $band = $sections[0];
        self::assertInstanceOf(ThemeComponentSection::class, $band);
        self::assertSame(PropType::Items, $band->schema()->props[1]->type);
        self::assertSame([['href' => '/new', 'label' => 'All the novelties']], $band->sample()['button'], 'The object of the example is held as one row.');

        $band->renderExample('en_US');

        self::assertSame(['title' => 'New in', 'button' => ['href' => '/new', 'label' => 'All the novelties']], $received);
    }

    private function catalogue(?array &$received): ThemeComponentSections
    {
        $theme = $this->createStub(TemplateDefinition::class);
        $theme->method('getAbsolutePath')->willReturn($this->directory);
        $theme->method('getParentList')->willReturn([]);
        $templates = $this->createStub(TemplateHelperInterface::class);
        $templates->method('getActiveFrontTemplate')->willReturn($theme);

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route): string => '/'.$route);
        $reader = new ComponentPropsReader(new Environment(new ArrayLoader(['@Flexy/Layouts/Band/Base.html.twig' => self::TEMPLATE])), $urls);

        $renderer = $this->createStub(ComponentRendererInterface::class);
        $renderer->method('createAndRender')->willReturnCallback(static function (string $name, array $props) use (&$received): string {
            $received = $props;

            return '<div></div>';
        });

        return new ThemeComponentSections($templates, $reader, $renderer, new NullLogger());
    }
}
