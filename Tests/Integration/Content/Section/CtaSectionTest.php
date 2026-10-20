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
use TheliaCMS\Content\Section\CtaSection;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * What the call to action section accepts, and the markup it renders.
 */
final class CtaSectionTest extends CmsIntegrationTestCase
{
    private CtaSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAnInstalledFrontTheme();
        $this->section = $this->getService(CtaSection::class);
    }

    public function testItIsKnownByItsName(): void
    {
        self::assertSame('Cms:Cta', CtaSection::NAME);
        self::assertSame(CtaSection::NAME, $this->section->name());
        self::assertNotSame('', $this->section->label());
        self::assertNotSame('', $this->section->description());
    }

    public function testItsSampleIsAcceptedByItsOwnSchema(): void
    {
        self::assertSame(['title', 'text', 'button_label', 'button_url'], array_keys($this->section->normalizeProps($this->section->sample())));
    }

    public function testEveryRequiredPropLeftOutIsRefused(): void
    {
        try {
            $this->section->normalizeProps([]);
            self::fail('The props should have been refused.');
        } catch (InvalidDocumentException $exception) {
            self::assertSame(['"title" is required.', '"button_label" is required.', '"button_url" is required.'], $exception->problems);
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

        self::assertStringContainsString('class="cms-cta"', $html);
        self::assertStringContainsString('class="cms-cta__body"', $html);
        self::assertStringContainsString('class="cms-cta__title"', $html);
        self::assertStringContainsString('class="cms-cta__text"', $html);
        self::assertStringContainsString('class="cms-cta__actions"', $html);
        self::assertStringContainsString('class="cms-button" href="/contact"', $html);
        self::assertStringContainsString('aria-labelledby="cms-cta-title-', $html);
        self::assertStringNotContainsString('data-gjs', $html);
    }

    public function testTheTextIsOptionalAndEscaped(): void
    {
        $without = $this->render(['title' => 'T', 'button_label' => 'Go', 'button_url' => '/go']);
        $escaped = $this->render(['title' => 'T', 'text' => '<i>x</i>', 'button_label' => 'Go', 'button_url' => '/go']);

        self::assertStringNotContainsString('cms-cta__text', $without);
        self::assertStringContainsString('&lt;i&gt;x&lt;/i&gt;', $escaped);
    }
}
