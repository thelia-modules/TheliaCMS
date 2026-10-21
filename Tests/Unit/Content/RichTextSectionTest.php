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

namespace TheliaCMS\Tests\Unit\Content;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\TwigComponent\ComponentRendererInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;
use TheliaCMS\Content\RichTextSection;
use TheliaCMS\Content\Theme\ComponentPropsReader;
use TheliaCMS\Content\Theme\ThemeComponentSections;
use Twig\Environment;

final class RichTextSectionTest extends TestCase
{
    public function testFreeTextCarriesTheClassesTheThemeStylesWrittenTextWith(): void
    {
        $directory = sys_get_temp_dir().'/theliacms-theme-'.bin2hex(random_bytes(4));
        mkdir($directory.'/config', 0o777, true);

        try {
            file_put_contents($directory.'/config/theliacms.yaml', "sections:\n    richtext_class: wysiwyg wysiwyg-theme\n    components: []\n");

            self::assertSame(
                '<div class="cms-richtext wysiwyg wysiwyg-theme"><ol><li>Un</li></ol></div>',
                (new RichTextSection(null, $this->themeIn($directory)))->render(['html' => '<ol><li>Un</li></ol>'], 'fr_FR'),
                'Lists and headings take the look the theme gives written text.',
            );
        } finally {
            @unlink($directory.'/config/theliacms.yaml');
            @rmdir($directory.'/config');
            @rmdir($directory);
        }
    }

    public function testWithoutATheme(): void
    {
        self::assertSame('<div class="cms-richtext"><p>Texte</p></div>', (new RichTextSection())->render(['html' => '<p>Texte</p>'], 'fr_FR'));
    }

    private function themeIn(string $directory): ThemeComponentSections
    {
        $theme = $this->createStub(TemplateDefinition::class);
        $theme->method('getAbsolutePath')->willReturn($directory);
        $theme->method('getParentList')->willReturn([]);

        $templates = $this->createStub(TemplateHelperInterface::class);
        $templates->method('getActiveFrontTemplate')->willReturn($theme);

        return new ThemeComponentSections(
            $templates,
            new ComponentPropsReader($this->createStub(Environment::class), $this->createStub(UrlGeneratorInterface::class)),
            $this->createStub(ComponentRendererInterface::class),
            new NullLogger(),
        );
    }
}
