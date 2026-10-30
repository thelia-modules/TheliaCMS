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

namespace TheliaCMS\Tests\Unit\Page\Document;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheliaCMS\Page\Document\PageDocumentDelivery;
use TheliaCMS\Page\Document\PageDocumentFormat;

/**
 * A document is served as what its extension says, and only a pdf or a
 * text is shown by the browser: anything else is saved.
 */
final class PageDocumentDeliveryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function extensions(): iterable
    {
        yield 'pdf' => ['pdf', 'application/pdf', true];
        yield 'txt' => ['txt', 'text/plain; charset=UTF-8', true];
        yield 'doc' => ['doc', 'application/msword', false];
        yield 'docx' => ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', false];
        yield 'xls' => ['xls', 'application/vnd.ms-excel', false];
        yield 'xlsx' => ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', false];
        yield 'ppt' => ['ppt', 'application/vnd.ms-powerpoint', false];
        yield 'pptx' => ['pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', false];
        yield 'odt' => ['odt', 'application/vnd.oasis.opendocument.text', false];
        yield 'zip' => ['zip', 'application/zip', false];
    }

    #[DataProvider('extensions')]
    public function testEachExtensionHasItsTypeAndItsDisposition(string $extension, string $type, bool $inline): void
    {
        self::assertSame($type, PageDocumentDelivery::contentType($extension));
        self::assertSame($inline, PageDocumentDelivery::isInline($extension));
    }

    public function testEveryAcceptedExtensionIsServed(): void
    {
        foreach (PageDocumentFormat::extensions() as $extension) {
            self::assertNotNull(PageDocumentDelivery::contentType($extension), $extension);
        }
    }

    public function testTheCaseOfTheExtensionChangesNothing(): void
    {
        self::assertSame('application/pdf', PageDocumentDelivery::contentType('PDF'));
        self::assertTrue(PageDocumentDelivery::isInline('TXT'));
    }

    /**
     * What a visitor's browser would run from the address of the site is never
     * a document: an extension out of the list has no type, and is not shown.
     */
    public function testAnExtensionOutOfTheListHasNoTypeAndIsNeverShown(): void
    {
        foreach (['html', 'htm', 'svg', 'js', 'php', ''] as $extension) {
            self::assertNull(PageDocumentDelivery::contentType($extension), $extension);
            self::assertFalse(PageDocumentDelivery::isInline($extension), $extension);
        }
    }
}
