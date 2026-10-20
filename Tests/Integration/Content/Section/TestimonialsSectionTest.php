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
use TheliaCMS\Content\Section\TestimonialsSection;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

final class TestimonialsSectionTest extends CmsIntegrationTestCase
{
    public function testItIsNamedAfterTheCatalogue(): void
    {
        self::assertSame('Cms:Testimonials', $this->section()->name());
    }

    public function testTheSampleNormalizesToTheDeclaredProps(): void
    {
        $props = $this->section()->normalizeProps($this->section()->sample());

        self::assertSame(['title', 'items'], array_keys($props));
        self::assertCount(3, $props['items']);
        self::assertSame(['quote', 'author'], array_keys($props['items'][0]));
    }

    public function testAnEmptyListIsRefused(): void
    {
        $this->expectException(InvalidDocumentException::class);

        $this->section()->normalizeProps(['title' => 'A title', 'items' => []]);
    }

    public function testAnItemWithoutItsRequiredPropIsRefused(): void
    {
        $this->expectException(InvalidDocumentException::class);

        $this->section()->normalizeProps(['title' => 'A title', 'items' => [['author' => 'Someone']]]);
    }

    public function testItRendersItsMarkup(): void
    {
        $this->useAnInstalledFrontTheme();
        $section = $this->section();

        $html = $section->render($section->normalizeProps($section->sample()), $this->locale());

        foreach (['class="cms-testimonials"', 'cms-testimonials__title', 'cms-testimonials__list', '<blockquote>', '<figcaption>'] as $class) {
            self::assertStringContainsString($class, $html);
        }
        self::assertSame(3, substr_count($html, 'class="cms-testimonials__item"'));
        self::assertSame(1, preg_match('/aria-labelledby="(cms-testimonials-title-[0-9a-f]{8})"/', $html, $matches));
        self::assertStringContainsString('id="'.$matches[1].'"', $html);
        self::assertStringNotContainsString('data-gjs', $html);
    }

    private function section(): TestimonialsSection
    {
        return $this->getService(TestimonialsSection::class);
    }
}
