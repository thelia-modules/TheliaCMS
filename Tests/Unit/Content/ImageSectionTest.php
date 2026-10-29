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
use TheliaCMS\Content\ImageSection;
use TheliaCMS\Content\InvalidDocumentException;

final class ImageSectionTest extends TestCase
{
    public function testAPictureOverItsCaption(): void
    {
        $section = new ImageSection();
        $props = $section->normalizeProps(['image' => 'https://example.com/tarte.jpg', 'alt' => 'Une tarte aux pommes dorée', 'caption' => 'Tout juste <b>sortie</b> du four', 'width' => 'narrow']);

        self::assertSame(
            '<figure class="cms-image cms-image--narrow"><img class="cms-image__img" src="https://example.com/tarte.jpg" alt="Une tarte aux pommes dorée" loading="lazy">'
            .'<figcaption class="cms-image__caption">Tout juste &lt;b&gt;sortie&lt;/b&gt; du four</figcaption></figure>',
            $section->render($props, 'fr_FR'),
            'The caption is written as text, never as markup.',
        );
    }

    public function testNoCaptionNoFigcaption(): void
    {
        $section = new ImageSection();

        self::assertSame(
            '<figure class="cms-image cms-image--narrow"><img class="cms-image__img" src="https://example.com/tarte.jpg" alt="" loading="lazy"></figure>',
            $section->render($section->normalizeProps(['image' => 'https://example.com/tarte.jpg']), 'fr_FR'),
            'As narrow as a column of text unless asked otherwise: a photo is not a banner.',
        );
    }

    public function testNoImageDrawsNothing(): void
    {
        $section = new ImageSection();

        self::assertSame('', $section->render($section->normalizeProps($section->sample()), 'fr_FR'), 'The sample is valid and draws nothing until an image is chosen.');
    }

    public function testAnImageOfElsewhereIsAnHttpsAddress(): void
    {
        $this->expectException(InvalidDocumentException::class);

        (new ImageSection())->normalizeProps(['image' => 'http://example.com/a.png']);
    }

    public function testAWidthOutsideTheChoicesIsRefused(): void
    {
        $this->expectException(InvalidDocumentException::class);

        (new ImageSection())->normalizeProps(['image' => 'https://example.com/a.png', 'width' => 'huge']);
    }
}
