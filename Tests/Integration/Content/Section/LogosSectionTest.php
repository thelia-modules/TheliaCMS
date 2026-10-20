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

namespace TheliaCMS\Tests\Integration\Content\Section;

use TheliaCMS\Content\InvalidDocumentException;
use TheliaCMS\Content\Section\LogosSection;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaLibrary\Model\LibraryImage;

final class LogosSectionTest extends CmsIntegrationTestCase
{
    public function testItIsNamedAfterTheCatalogue(): void
    {
        self::assertSame('Cms:Logos', $this->section()->name());
    }

    public function testTheSampleNormalizesToTheDeclaredProps(): void
    {
        $sample = $this->section()->sample();

        self::assertSame(['title', 'items'], array_keys($sample));
        self::assertCount(4, $sample['items']);
        self::assertSame(['image', 'alt', 'url'], array_keys($sample['items'][0]));

        $props = $this->section()->normalizeProps($sample);

        self::assertSame(['title', 'items'], array_keys($props));
        self::assertCount(4, $props['items']);
    }

    public function testAnEmptyListIsRefused(): void
    {
        $this->expectException(InvalidDocumentException::class);

        $this->section()->normalizeProps(['title' => 'A title', 'items' => []]);
    }

    public function testAnItemWithAnInvalidImageIsRefused(): void
    {
        $this->expectException(InvalidDocumentException::class);

        $this->section()->normalizeProps(['title' => 'A title', 'items' => [['image' => 'not an id']]]);
    }

    public function testItRendersItsMarkup(): void
    {
        $this->useAnInstalledFrontTheme();
        $section = $this->section();

        $html = $section->render($section->normalizeProps($section->sample()), $this->locale());

        foreach (['class="cms-logos"', 'cms-logos__title', 'cms-logos__list', '<img'] as $class) {
            self::assertStringContainsString($class, $html);
        }
        self::assertSame(4, substr_count($html, 'class="cms-logos__item"'));
        self::assertSame(1, preg_match('/aria-labelledby="(cms-logos-title-[0-9a-f]{8})"/', $html, $matches));
        self::assertStringContainsString('id="'.$matches[1].'"', $html);
        self::assertStringNotContainsString('data-gjs', $html);
    }

    public function testAnItemWithAnAddressWrapsItsLogoInALink(): void
    {
        $this->useAnInstalledFrontTheme();
        $section = $this->section();
        $props = $section->normalizeProps([
            'title' => 'Partners',
            'items' => [
                ['image' => $this->anImage(), 'alt' => 'Acme', 'url' => 'https://example.com'],
                ['image' => $this->anImage(), 'alt' => 'Globex'],
            ],
        ]);

        $html = $section->render($props, $this->locale());

        self::assertSame(1, substr_count($html, '<a href="https://example.com">'));
        self::assertStringContainsString('alt="Acme"', $html);
        self::assertStringContainsString('alt="Globex"', $html);
    }

    private function anImage(): int
    {
        $image = new LibraryImage();
        $image->setLocale($this->locale())->setFileName('logos-section-test.png')->setTitle('Logo')->setWidth(200)->setHeight(100);
        $image->save();

        return (int) $image->getId();
    }

    private function section(): LogosSection
    {
        return $this->getService(LogosSection::class);
    }
}
