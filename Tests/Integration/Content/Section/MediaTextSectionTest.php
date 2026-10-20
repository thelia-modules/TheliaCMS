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
use TheliaCMS\Content\Section\MediaTextSection;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * What the text and image section accepts, and the markup it renders.
 */
final class MediaTextSectionTest extends CmsIntegrationTestCase
{
    private MediaTextSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAnInstalledFrontTheme();
        $this->section = $this->getService(MediaTextSection::class);
    }

    public function testItIsKnownByItsName(): void
    {
        self::assertSame('Cms:MediaText', MediaTextSection::NAME);
        self::assertSame(MediaTextSection::NAME, $this->section->name());
        self::assertNotSame('', $this->section->label());
        self::assertNotSame('', $this->section->description());
    }

    public function testItsSampleIsAcceptedByItsOwnSchema(): void
    {
        self::assertSame(['title', 'text', 'image', 'image_alt', 'link_label', 'link_url'], array_keys($this->section->normalizeProps($this->section->sample())));
    }

    public function testEveryRequiredPropLeftOutIsRefused(): void
    {
        try {
            $this->section->normalizeProps([]);
            self::fail('The props should have been refused.');
        } catch (InvalidDocumentException $exception) {
            self::assertSame(['"title" is required.'], $exception->problems);
        }
    }

    /**
     * @param array<string, mixed> $props
     */
    private function render(array $props): string
    {
        return $this->section->render($this->section->normalizeProps($props), $this->locale());
    }

    public function testItsSampleIsRenderedWithTheMarkupTheStylesheetKnows(): void
    {
        $html = $this->render($this->section->sample());

        self::assertStringContainsString('class="cms-media-text"', $html);
        self::assertStringContainsString('class="cms-media-text__body"', $html);
        self::assertStringContainsString('class="cms-media-text__title"', $html);
        self::assertStringContainsString('class="cms-link"', $html);
        self::assertStringContainsString('aria-labelledby="cms-media-text-title-', $html);
        self::assertStringNotContainsString('data-gjs', $html);
        self::assertStringNotContainsString('<img', $html, 'The sample names no image, so none is printed.');
        self::assertStringNotContainsString('cms-media-text__media', $html);
    }

    public function testTheLinkNeedsBothItsLabelAndItsAddress(): void
    {
        $withoutAddress = $this->render(['title' => 'T', 'link_label' => 'Read more']);
        $withoutLabel = $this->render(['title' => 'T', 'link_url' => '/contact']);

        self::assertStringNotContainsString('<a ', $withoutAddress);
        self::assertStringNotContainsString('<a ', $withoutLabel);
    }

    public function testTheTitleIsEscapedAndTheTextIsPrintedAsMarkup(): void
    {
        $html = $this->render(['title' => '<b>T</b>', 'text' => '<p>One</p><ul><li>Two</li></ul>']);

        self::assertStringContainsString('&lt;b&gt;T&lt;/b&gt;', $html);
        self::assertStringContainsString('<p>One</p><ul><li>Two</li></ul>', $html);
    }
}
