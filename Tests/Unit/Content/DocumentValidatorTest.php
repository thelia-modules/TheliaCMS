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
use TheliaCMS\Content\DocumentValidator;
use TheliaCMS\Content\InvalidDocumentException;
use TheliaCMS\Content\RichTextSection;
use TheliaCMS\Content\Section;
use TheliaCMS\Content\SectionCatalog;

/**
 * What stands between whoever wrote a document — an editor, an import, a
 * model — and the database: every section must be one the catalogue knows,
 * with props its component accepts and an id a later edit can name.
 */
final class DocumentValidatorTest extends TestCase
{
    private DocumentValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DocumentValidator(new SectionCatalog([new RichTextSection(), new FakeSection()]));
    }

    public function testAValidDocumentComesBackWithItsPropsNormalized(): void
    {
        $document = new Document([
            new Section('hero', FakeSection::NAME, ['title' => 'Bonjour', 'unknown' => 'dropped']),
            new Section('intro', RichTextSection::NAME, ['html' => '<p>Texte</p>']),
        ]);

        $validated = $this->validator->validate($document);

        self::assertSame(['title' => 'Bonjour', 'level' => 2], $validated->sections[0]->props, 'Unknown props are dropped, missing ones get their default.');
        self::assertSame(['html' => '<p>Texte</p>'], $validated->sections[1]->props);
    }

    public function testEveryProblemIsListedAtOnce(): void
    {
        $document = new Document([
            new Section('Hero!', 'Cms:Missing', []),
            new Section('intro', FakeSection::NAME, ['title' => 42]),
            new Section('intro', RichTextSection::NAME, ['html' => ['not', 'a', 'string']]),
        ]);

        try {
            $this->validator->validate($document);
            self::fail('The document should have been refused.');
        } catch (InvalidDocumentException $exception) {
            self::assertCount(5, $exception->problems);
            self::assertStringContainsString('Section 1 ("Hero!") has an id that is not', $exception->problems[0]);
            self::assertStringContainsString('"Cms:Missing", which is not a component', $exception->problems[1]);
            self::assertStringContainsString('Section 2 ("intro"): The title must be a string.', $exception->problems[2]);
            self::assertStringContainsString('Section 3 ("intro") repeats the id of section 2', $exception->problems[3]);
            self::assertStringContainsString('Section 3 ("intro"): "html" must be a string of markup.', $exception->problems[4]);
        }
    }

    public function testAnEmptyDocumentIsValid(): void
    {
        self::assertTrue($this->validator->validate(new Document([]))->isEmpty());
    }
}
