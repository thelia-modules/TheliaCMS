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
use TheliaCMS\Content\Section\SectionSection;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * What the titled section accepts, and the markup it renders.
 */
final class SectionSectionTest extends CmsIntegrationTestCase
{
    private SectionSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAnInstalledFrontTheme();
        $this->section = $this->getService(SectionSection::class);
    }

    public function testItIsKnownByItsName(): void
    {
        self::assertSame('Cms:Section', SectionSection::NAME);
        self::assertSame(SectionSection::NAME, $this->section->name());
        self::assertNotSame('', $this->section->label());
        self::assertNotSame('', $this->section->description());
    }

    public function testItsSampleIsAcceptedByItsOwnSchema(): void
    {
        self::assertSame(['title', 'text'], array_keys($this->section->normalizeProps($this->section->sample())));
    }

    public function testEveryRequiredPropLeftOutIsRefused(): void
    {
        try {
            $this->section->normalizeProps([]);
            self::fail('The props should have been refused.');
        } catch (InvalidDocumentException $exception) {
            self::assertSame(['"title" is required.', '"text" is required.'], $exception->problems);
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

        self::assertStringContainsString('class="cms-section"', $html);
        self::assertStringContainsString('class="cms-section__title"', $html);
        self::assertStringContainsString('class="cms-section__body"', $html);
        self::assertStringContainsString('aria-labelledby="cms-section-title-', $html);
        self::assertStringNotContainsString('data-gjs', $html);
    }

    public function testTheTitleIsEscapedAndTheContentIsPrintedAsMarkup(): void
    {
        $html = $this->render(['title' => '<b>T</b>', 'text' => '<h3>Sub</h3><p>One</p>']);

        self::assertStringContainsString('&lt;b&gt;T&lt;/b&gt;', $html);
        self::assertStringContainsString('<h3>Sub</h3><p>One</p>', $html);
    }
}
