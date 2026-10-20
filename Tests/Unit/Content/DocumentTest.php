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
use TheliaCMS\Content\Document;
use TheliaCMS\Content\InvalidDocumentException;
use TheliaCMS\Content\RichTextSection;
use TheliaCMS\Content\Section;

/**
 * The stored form of a page: what goes into the column must come back out the
 * same, and a column holding something else must be refused rather than read
 * as an empty page.
 */
final class DocumentTest extends TestCase
{
    public function testARichTextDocumentHoldsOneSectionCarryingTheMarkup(): void
    {
        $document = Document::richText('<h1>Bonjour</h1>');

        self::assertCount(1, $document->sections);
        self::assertSame(RichTextSection::NAME, $document->sections[0]->component);
        self::assertSame(['html' => '<h1>Bonjour</h1>'], $document->sections[0]->props);
        self::assertFalse($document->isEmpty());
    }

    public function testADocumentSurvivesTheRoundTripThroughJson(): void
    {
        $document = new Document([
            new Section('hero', 'Cms:Hero', ['title' => 'Nos studios 📷', 'image' => 12, 'url' => '/image-library/12/full/960/0/default.webp']),
            new Section('intro', RichTextSection::NAME, ['html' => '<p>Texte</p>']),
        ]);

        $json = $document->toJson();
        $read = Document::fromJson($json);

        self::assertNotNull($read);
        self::assertSame($document->toArray(), $read->toArray());
        self::assertStringContainsString('/image-library/12/', $json, 'Image addresses are stored the way the HTML spells them, so usage finders see them.');
        self::assertStringContainsString('📷', $json, 'Characters are stored as themselves, not as \u escapes.');
        self::assertSame(Document::VERSION, $read->toArray()['version']);
    }

    public function testAnEmptyColumnIsNoDocument(): void
    {
        self::assertNull(Document::fromJson(null));
        self::assertNull(Document::fromJson(''));
        self::assertNull(Document::fromJson('   '));
    }

    public function testAColumnHoldingSomethingElseIsRefused(): void
    {
        $this->expectException(InvalidDocumentException::class);

        Document::fromJson('{"pages":[]}');
    }

    public function testBrokenJsonIsRefused(): void
    {
        $this->expectException(InvalidDocumentException::class);
        $this->expectExceptionMessage('not valid JSON');

        Document::fromJson('{"version":1,"sections":[');
    }

    public function testADocumentFromALaterFormatIsRefused(): void
    {
        $this->expectException(InvalidDocumentException::class);
        $this->expectExceptionMessage('format version 2');

        Document::fromArray(['version' => 2, 'sections' => []]);
    }

    public function testEveryMalformedSectionIsReportedAtOnce(): void
    {
        try {
            Document::fromArray(['version' => 1, 'sections' => [
                ['component' => 'Cms:Hero', 'props' => []],
                ['id' => 'b', 'props' => []],
                ['id' => 'c', 'component' => 'Cms:Hero', 'props' => ['a', 'b']],
                'not a section',
            ]]);
            self::fail('The document should have been refused.');
        } catch (InvalidDocumentException $exception) {
            self::assertCount(4, $exception->problems);
            self::assertStringContainsString('Section 1 has no id', $exception->problems[0]);
            self::assertStringContainsString('Section 2 names no component', $exception->problems[1]);
            self::assertStringContainsString('Section 3 has props that are not an object', $exception->problems[2]);
            self::assertStringContainsString('Section 4 is not an object', $exception->problems[3]);
        }
    }

    public function testASectionIsReplacedByIdAndTheOthersAreLeftAlone(): void
    {
        $document = new Document([
            new Section('hero', 'Cms:Hero', ['title' => 'Avant']),
            new Section('intro', RichTextSection::NAME, ['html' => '<p>Texte</p>']),
        ]);

        $changed = $document->withSection(new Section('hero', 'Cms:Hero', ['title' => 'Après']));

        self::assertSame('Après', $changed->section('hero')?->props['title']);
        self::assertSame('<p>Texte</p>', $changed->section('intro')?->props['html']);
        self::assertSame('Avant', $document->section('hero')?->props['title'], 'The original is not changed.');
        self::assertNull($changed->section('missing'));
    }
}
