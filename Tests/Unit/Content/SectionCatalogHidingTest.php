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
use Symfony\UX\TwigComponent\ComponentRendererInterface;
use TheliaCMS\Content\PropSchema;
use TheliaCMS\Content\RichTextSection;
use TheliaCMS\Content\SectionCatalog;
use TheliaCMS\Content\SectionHidingInterface;
use TheliaCMS\Content\SectionProviderInterface;
use TheliaCMS\Content\Theme\ThemeComponentSection;

/**
 * A theme that opens a component of its own for something puts the
 * module's section for it away, and never its own component.
 */
final class SectionCatalogHidingTest extends TestCase
{
    public function testHiddenSectionsLeaveTheCatalogueButNotTheComponentsOfTheTheme(): void
    {
        $theme = new ThemeComponentSection('Layouts:CrossSelling:Base', '', new PropSchema([]), [], $this->createStub(ComponentRendererInterface::class));
        $provider = new class($theme) implements SectionProviderInterface, SectionHidingInterface {
            public function __construct(private readonly ThemeComponentSection $theme)
            {
            }

            public function sections(): iterable
            {
                yield $this->theme;
            }

            public function hidden(): array
            {
                return [FakeSection::NAME, 'Layouts:CrossSelling:Base', 'Cms:Nothing'];
            }
        };

        $catalog = new SectionCatalog([new RichTextSection(), new FakeSection()], [$provider]);

        self::assertSame([RichTextSection::NAME, 'Layouts:CrossSelling:Base'], $catalog->names());
    }
}
