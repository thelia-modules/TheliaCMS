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
use TheliaCMS\Content\Section\QuoteSection;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * What the quote section accepts, and the markup it renders.
 */
final class QuoteSectionTest extends CmsIntegrationTestCase
{
    private QuoteSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAnInstalledFrontTheme();
        $this->section = $this->getService(QuoteSection::class);
    }

    public function testItIsKnownByItsName(): void
    {
        self::assertSame('Cms:Quote', QuoteSection::NAME);
        self::assertSame(QuoteSection::NAME, $this->section->name());
        self::assertNotSame('', $this->section->label());
        self::assertNotSame('', $this->section->description());
    }

    public function testItsSampleIsAcceptedByItsOwnSchema(): void
    {
        self::assertSame(['text', 'author'], array_keys($this->section->normalizeProps($this->section->sample())));
    }

    public function testEveryRequiredPropLeftOutIsRefused(): void
    {
        try {
            $this->section->normalizeProps([]);
            self::fail('The props should have been refused.');
        } catch (InvalidDocumentException $exception) {
            self::assertSame(['"text" is required.'], $exception->problems);
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

        self::assertStringContainsString('<figure class="cms-quote">', $html);
        self::assertStringContainsString('<blockquote class="cms-quote__text"><p>', $html);
        self::assertStringContainsString('<figcaption class="cms-quote__author">', $html);
        self::assertStringNotContainsString('data-gjs', $html);
    }

    public function testWithoutAnAuthorThereIsNoCaption(): void
    {
        $html = $this->render(['text' => 'A <b>sentence</b>']);

        self::assertStringNotContainsString('figcaption', $html);
        self::assertStringContainsString('A &lt;b&gt;sentence&lt;/b&gt;', $html);
    }
}
