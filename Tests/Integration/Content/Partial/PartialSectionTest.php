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

namespace TheliaCMS\Tests\Integration\Content\Partial;

use TheliaCMS\Content\Document;
use TheliaCMS\Content\InvalidDocumentException;
use TheliaCMS\Content\Partial\PartialSection;
use TheliaCMS\Content\PropType;
use TheliaCMS\Content\Section;
use TheliaCMS\Content\SectionCatalog;
use TheliaCMS\Content\Theme\ThemeComponentSections;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * The partials of the site reach a document as sections: the page stores the
 * name and the settings, and the placeholder they render is what the partial
 * renderer substitutes when the page is served.
 */
final class PartialSectionTest extends CmsIntegrationTestCase
{
    public function testEveryPartialOfTheSiteIsASectionOfTheCatalogue(): void
    {
        $names = $this->getService(SectionCatalog::class)->names();

        self::assertContains('Partial:cms-block', $names);
        self::assertContains('Partial:cms-form', $names);
        // Unless the theme puts it away for a component of its own.
        $hidden = $this->getService(ThemeComponentSections::class)->hidden();
        self::assertSame(\in_array('Partial:product-card', $hidden, true), !\in_array('Partial:product-card', $names, true));
    }

    public function testThePropsOfAPartialBecomeTheSchemaOfTheSection(): void
    {
        $section = $this->getService(SectionCatalog::class)->find('Partial:latest-contents');

        self::assertInstanceOf(PartialSection::class, $section);

        $props = [];

        foreach ($section->schema()->props as $prop) {
            $props[$prop->name] = $prop;
        }

        self::assertSame(PropType::Integer, $props['count']->type);
        self::assertSame(3, $props['count']->default);
        self::assertSame(PropType::Reference, $props['folder']->type);
        self::assertFalse($props['folder']->required);
        self::assertStringStartsWith('folder.', (string) $props['folder']->help);
        self::assertSame(PropType::Text, $props['heading']->type);
        self::assertStringContainsString('Live content', $section->description());
    }

    public function testASectionRendersThePlaceholderThePageServesThrough(): void
    {
        $section = $this->getService(SectionCatalog::class)->find('Partial:cms-block');

        self::assertNotNull($section);

        $html = $section->render($section->normalizeProps(['block' => 7]), $this->locale());

        self::assertSame('<div data-cms-partial="cms-block" data-props="{&quot;block&quot;:7}"></div>', $html);
    }

    public function testAPartialNeedsItsRequiredReference(): void
    {
        $section = $this->getService(SectionCatalog::class)->find('Partial:cms-block');

        self::assertNotNull($section);
        $this->expectException(InvalidDocumentException::class);
        $this->expectExceptionMessage('"block" is required.');

        $section->normalizeProps([]);
    }

    public function testAPageHoldingAPartialSectionIsPublishedWithThePlaceholderAndServedResolved(): void
    {
        $page = $this->createPage('Page avec bloc dynamique', published: false);
        $this->writer()->saveContent($page, $this->locale(), new Document([
            new Section('intro', 'cms:richtext', ['html' => '<h1>Formulaire</h1>']),
            new Section('form', 'Partial:cms-form', ['form' => 1]),
        ]));
        $this->writer()->publish($page, $this->locale());

        $content = CmsPageContentQuery::create()->filterByPageId($page->getId())->filterByLocale($this->locale())->findOne();

        self::assertNotNull($content);
        self::assertStringContainsString('data-cms-partial="cms-form"', (string) $content->getPublishedHtml(), 'The placeholder survives the sanitizer and is what the page stores.');
        // The sanitizer rewrites the attribute with the quotes it likes; the
        // settings inside are what matters.
        self::assertStringContainsString('{"form":1}', html_entity_decode((string) $content->getPublishedHtml(), \ENT_QUOTES));
    }
}
