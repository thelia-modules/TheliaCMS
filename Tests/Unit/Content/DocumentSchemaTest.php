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
use TheliaCMS\Content\DocumentSchema;
use TheliaCMS\Content\RichTextSection;
use TheliaCMS\Content\SectionCatalog;

/**
 * The full schema documents and validates the catalogue; the strict one is
 * the small envelope a generator can be constrained with.
 */
final class DocumentSchemaTest extends TestCase
{
    private DocumentSchema $schema;

    protected function setUp(): void
    {
        $this->schema = new DocumentSchema(new SectionCatalog([new RichTextSection(), new FakeSection()]));
    }

    public function testTheFullSchemaDescribesOneAlternativePerComponent(): void
    {
        $full = $this->schema->full();

        self::assertSame(['version', 'sections'], $full['required']);
        self::assertSame(1, $full['properties']['version']['const']);

        $alternatives = $full['properties']['sections']['items']['anyOf'];

        self::assertCount(2, $alternatives);
        self::assertSame(RichTextSection::NAME, $alternatives[0]['properties']['component']['const']);
        self::assertSame(FakeSection::NAME, $alternatives[1]['properties']['component']['const']);
        self::assertFalse($alternatives[1]['additionalProperties']);
        self::assertSame(['id', 'component', 'props'], $alternatives[1]['required']);
        self::assertSame(['title'], $alternatives[1]['properties']['props']['required']);
        self::assertSame(1, ((array) $alternatives[1]['properties']['props']['properties'])['level']['minimum'], 'The full schema keeps the ranges.');
    }

    public function testTheStrictSchemaIsTheEnvelopeAlone(): void
    {
        $strict = $this->schema->strict();
        $json = json_encode($strict, \JSON_THROW_ON_ERROR);

        foreach (['minimum', 'maximum', 'maxLength', 'minLength', 'minItems', 'maxItems', 'anyOf', 'null'] as $keyword) {
            self::assertStringNotContainsString('"'.$keyword.'"', $json, $keyword.' has no place in a grammar the API compiles.');
        }

        self::assertInstanceOf(\stdClass::class, $strict['properties'], 'An object with properties stays a JSON object, never a list.');

        $section = $strict['properties']->sections['items'];

        self::assertSame(['id', 'component', 'props'], $section['required']);
        self::assertSame([RichTextSection::NAME, FakeSection::NAME], $section['properties']->component['enum']);
        self::assertSame('string', $section['properties']->props['type'], 'The props travel as a JSON string the validator reads back.');
        self::assertSame(1, $strict['properties']->version['const']);
    }
}
