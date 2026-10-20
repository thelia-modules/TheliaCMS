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
use TheliaCMS\Content\Section\HeroSection;
use TheliaCMS\Content\SectionCatalog;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\Twig\CmsExtension;

/**
 * The hero, rendered through the real template chain: same markup as the
 * block it replaces, so the stylesheet of the module still dresses it.
 */
final class HeroSectionTest extends CmsIntegrationTestCase
{
    public function testTheCatalogueKnowsIt(): void
    {
        self::assertInstanceOf(HeroSection::class, $this->getService(SectionCatalog::class)->find(HeroSection::NAME));
    }

    public function testTheCatalogueIsListedForTheToolkitWithLiveContentToldApart(): void
    {
        $catalogue = $this->getService(CmsExtension::class)->catalogue();
        $byName = array_column($catalogue, null, 'name');

        self::assertArrayHasKey(HeroSection::NAME, $byName);
        self::assertFalse($byName[HeroSection::NAME]['live']);
        self::assertNotSame('', $byName[HeroSection::NAME]['description']);
        self::assertArrayHasKey('Partial:product-strip', $byName);
        self::assertTrue($byName['Partial:product-strip']['live'], 'Live content is named, not drawn.');
    }

    public function testTheSampleRendersTheMarkupOfTheFormerBlock(): void
    {
        $this->useAnInstalledFrontTheme();
        $section = $this->getService(HeroSection::class);

        $html = $section->render($section->normalizeProps($section->sample()), $this->locale());

        self::assertMatchesRegularExpression('#<section class="cms-hero" aria-labelledby="cms-hero-title-[a-f0-9]{8}">#', $html);
        self::assertStringContainsString('<h1 class="cms-hero__title" id="cms-hero-title-', $html);
        self::assertStringContainsString('<p class="cms-hero__text">', $html);
        self::assertStringContainsString('<a class="cms-button" href="/contact">', $html);
        self::assertStringNotContainsString('data-gjs', $html);
    }

    public function testTextIsEscapedAndTheButtonGoesWithItsAddress(): void
    {
        $this->useAnInstalledFrontTheme();
        $section = $this->getService(HeroSection::class);

        $html = $section->render($section->normalizeProps([
            'title' => 'Tom & Jerry <script>',
            'heading_level' => '2',
            'cta_label' => 'Go',
        ]), $this->locale());

        self::assertStringContainsString('<h2 class="cms-hero__title"', $html);
        self::assertStringContainsString('Tom &amp; Jerry &lt;script&gt;', $html);
        self::assertStringNotContainsString('cms-hero__actions', $html, 'A label without an address is no button.');
        self::assertStringNotContainsString('cms-hero__text', $html, 'No text, no paragraph.');
    }

    public function testAHeroWithoutAHeadingIsRefused(): void
    {
        $this->expectException(InvalidDocumentException::class);
        $this->expectExceptionMessage('"title" is required.');

        $this->getService(HeroSection::class)->normalizeProps(['text' => 'Only a line']);
    }

    public function testTheSameRenderingComesOutOfTheTwigFunction(): void
    {
        $this->useAnInstalledFrontTheme();

        $html = $this->getService(\Twig\Environment::class)->createTemplate('{{ cms_section("Cms:Hero", {title: "Bonjour"}) }}')->render();

        self::assertStringContainsString('<h1 class="cms-hero__title" id="cms-hero-title-', $html);
        self::assertStringContainsString('Bonjour', $html);
        self::assertSame('', $this->getService(\Twig\Environment::class)->createTemplate('{{ cms_section("Nope:Missing") }}')->render());
    }
}
