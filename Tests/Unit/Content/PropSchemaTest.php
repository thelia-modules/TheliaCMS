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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheliaCMS\Content\InvalidDocumentException;
use TheliaCMS\Content\Prop;
use TheliaCMS\Content\PropSchema;

/**
 * What a section accepts from whoever wrote its props: strict on types and
 * ranges, forgiving on what is missing, and every problem told at once.
 */
final class PropSchemaTest extends TestCase
{
    private PropSchema $schema;

    protected function setUp(): void
    {
        $this->schema = new PropSchema([
            Prop::text('title', 'Title', required: true, max: 20),
            Prop::html('body', 'Body'),
            Prop::integer('count', 'Count', 3, 1, 12),
            Prop::boolean('open', 'Open'),
            Prop::choice('tone', 'Tone', ['light' => 'Light', 'dark' => 'Dark'], 'light'),
            Prop::image('picture', 'Picture'),
            Prop::url('link', 'Link'),
            Prop::items('items', 'Items', [
                Prop::text('label', 'Label', required: true),
                Prop::image('image', 'Image', required: true),
            ], min: 1, max: 2),
        ]);
    }

    public function testMissingPropsGetTheirDefaultAndUnknownOnesAreDropped(): void
    {
        $values = $this->schema->normalize([
            'title' => '  Hello  ',
            'items' => [['label' => 'One', 'image' => '7', 'extra' => 'dropped']],
            'extra' => 'dropped',
        ]);

        self::assertSame([
            'title' => 'Hello',
            'body' => '',
            'count' => 3,
            'open' => false,
            'tone' => 'light',
            'picture' => null,
            'link' => '',
            'items' => [['label' => 'One', 'image' => 7]],
        ], $values);
    }

    public function testValuesAreReadInTheFormsAnAuthorWritesThem(): void
    {
        $values = $this->schema->normalize([
            'title' => 'T',
            'count' => '12',
            'open' => 'true',
            'tone' => 'dark',
            'picture' => 42,
            'link' => 'https://example.test/a?b=c',
            'items' => [['label' => 'One', 'image' => 7], ['label' => 'Two', 'image' => 8]],
        ]);

        self::assertSame(12, $values['count']);
        self::assertTrue($values['open']);
        self::assertSame('dark', $values['tone']);
        self::assertSame(42, $values['picture']);
    }

    public function testEveryProblemIsListedAtOnce(): void
    {
        try {
            $this->schema->normalize([
                'title' => 'A title that is far too long for the limit',
                'count' => 13,
                'open' => 'maybe',
                'tone' => 'neon',
                'picture' => 'cat.jpg',
                'link' => 'javascript:alert(1)',
                'items' => [['label' => '', 'image' => 7], 'not an item', ['label' => 'Three', 'image' => 9]],
            ]);
            self::fail('The props should have been refused.');
        } catch (InvalidDocumentException $exception) {
            self::assertSame([
                '"title" is longer than 20 characters.',
                '"count" must be between 1 and 12.',
                '"open" must be true or false.',
                '"tone" must be one of light, dark.',
                '"picture" must be the id of an image of the media library.',
                '"link" must be a path of the site or an absolute http(s), mailto or tel address.',
                '"items" must hold between 1 and 2 items.',
            ], $exception->problems);
        }
    }

    public function testProblemsInsideItemsNameTheItem(): void
    {
        try {
            $this->schema->normalize(['title' => 'T', 'items' => [['label' => '', 'image' => 7], 'not an item']]);
            self::fail('The props should have been refused.');
        } catch (InvalidDocumentException $exception) {
            self::assertSame([
                '"items" item 1: "label" is required.',
                '"items" item 2 is not an object.',
            ], $exception->problems);
        }
    }

    public function testZeroIsNoPictureYetUnlessThePictureIsRequired(): void
    {
        $optional = new PropSchema([Prop::image('picture', 'Picture')]);
        $required = new PropSchema([Prop::image('picture', 'Picture', required: true)]);

        self::assertNull($optional->normalize(['picture' => 0])['picture']);
        self::assertNull($optional->normalize(['picture' => '0'])['picture']);

        $this->expectException(InvalidDocumentException::class);
        $this->expectExceptionMessage('"picture" is required.');

        $required->normalize(['picture' => 0]);
    }

    public function testARequiredPropLeftEmptyIsRefused(): void
    {
        $this->expectException(InvalidDocumentException::class);
        $this->expectExceptionMessage('"title" is required.');

        $this->schema->normalize(['title' => '', 'items' => [['label' => 'One', 'image' => 7]]]);
    }

    #[DataProvider('addresses')]
    public function testOnlyAddressesAPageMaySendAVisitorToAreAccepted(string $url, bool $accepted): void
    {
        $schema = new PropSchema([Prop::url('link', 'Link')]);

        if (!$accepted) {
            $this->expectException(InvalidDocumentException::class);
        }

        self::assertSame($url, $schema->normalize(['link' => $url])['link']);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function addresses(): iterable
    {
        yield 'a path of the site' => ['/nos-services', true];
        yield 'an anchor' => ['#contact', true];
        yield 'an absolute address' => ['https://example.test/', true];
        yield 'a mail link' => ['mailto:hello@example.test', true];
        yield 'a phone link' => ['tel:+33102030405', true];
        yield 'a script' => ['javascript:alert(1)', false];
        yield 'a protocol-relative address' => ['//evil.test/', false];
        yield 'a bare word' => ['contact', false];
    }

    public function testTheJsonSchemaDescribesEveryPropForAGenerator(): void
    {
        $schema = $this->schema->toJsonSchema();

        self::assertSame('object', $schema['type']);
        self::assertSame(['title', 'items'], $schema['required']);
        self::assertFalse($schema['additionalProperties']);

        $properties = (array) $schema['properties'];

        self::assertSame(['type' => 'string', 'maxLength' => 20], array_diff_key($properties['title'], ['description' => true]));
        self::assertSame(['light', 'dark'], $properties['tone']['enum']);
        self::assertSame(['integer', 'null'], $properties['picture']['type']);
        self::assertSame(1, $properties['items']['minItems']);
        self::assertSame(['label', 'image'], $properties['items']['items']['required']);
        self::assertSame('integer', ((array) $properties['items']['items']['properties'])['image']['type']);
    }
}
