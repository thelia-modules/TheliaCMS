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
use TheliaCMS\Page\Document\PageDocumentFormat;

/**
 * A file is attached to a page when its extension is one of the list and the
 * type read from its bytes is one that extension can have. The types are the
 * ones fileinfo gave for real files (see PageDocumentFormat).
 */
final class PageDocumentFormatTest extends TestCase
{
    private const string ZIP = "PK\x03\x04\x14\x00";

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function measuredFiles(): iterable
    {
        yield 'pdf' => ['pdf', 'application/pdf'];
        yield 'doc' => ['doc', 'application/msword'];
        yield 'xls' => ['xls', 'application/vnd.ms-excel'];
        yield 'ppt' => ['ppt', 'application/vnd.ms-powerpoint'];
        yield 'docx' => ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        yield 'xlsx' => ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        yield 'pptx' => ['pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
        yield 'odt' => ['odt', 'application/vnd.oasis.opendocument.text'];
        yield 'zip' => ['zip', 'application/zip'];
        yield 'txt' => ['txt', 'text/plain'];
        yield 'odt with its mimetype entry compressed' => ['odt', 'application/zip'];
        yield 'docx whose entries are out of the expected order' => ['docx', 'application/zip'];
        yield 'doc of an application libmagic cannot name' => ['doc', 'application/x-ole-storage'];
    }

    #[DataProvider('measuredFiles')]
    public function testAFileOfAnAcceptedFormatIsAccepted(string $extension, string $mimeType): void
    {
        self::assertTrue(PageDocumentFormat::accepts($extension, $mimeType, self::ZIP));
    }

    public function testTheExtensionIsReadWhateverItsCase(): void
    {
        self::assertSame('pdf', PageDocumentFormat::extensionOf('Fiche Technique.PDF'));
        self::assertTrue(PageDocumentFormat::accepts('PDF', 'application/pdf', '%PDF-'));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function refusedFiles(): iterable
    {
        yield 'html' => ['html', 'text/html'];
        yield 'svg' => ['svg', 'image/svg+xml'];
        yield 'php' => ['php', 'text/x-php'];
        yield 'an HTML page named pdf' => ['pdf', 'text/html'];
        yield 'a PHP script named txt' => ['txt', 'text/x-php'];
        yield 'a PDF named docx' => ['docx', 'application/pdf'];
        yield 'an SVG named pdf' => ['pdf', 'image/svg+xml'];
        yield 'a zip named pdf' => ['pdf', 'application/zip'];
        yield 'a zip named txt' => ['txt', 'application/zip'];
        yield 'a Word 97 file named xlsx' => ['xlsx', 'application/msword'];
        yield 'no extension' => ['', 'application/pdf'];
        yield 'no type' => ['pdf', null];
    }

    #[DataProvider('refusedFiles')]
    public function testAFileWhoseBytesDoNotMatchItsExtensionIsRefused(string $extension, ?string $mimeType): void
    {
        self::assertFalse(PageDocumentFormat::accepts($extension, $mimeType, self::ZIP));
    }

    public function testAStreamedZipIsAcceptedOnlyWhenItStartsAsAZip(): void
    {
        self::assertTrue(PageDocumentFormat::accepts('docx', 'application/octet-stream', self::ZIP), 'A docx written as a stream reads as octet-stream.');
        self::assertTrue(PageDocumentFormat::accepts('zip', 'application/octet-stream', self::ZIP));
        self::assertFalse(PageDocumentFormat::accepts('docx', 'application/octet-stream', "MZ\x90\x00"), 'Any binary named docx.');
        self::assertFalse(PageDocumentFormat::accepts('pdf', 'application/octet-stream', self::ZIP), 'A pdf is never a zip.');
        self::assertFalse(PageDocumentFormat::accepts('txt', 'application/octet-stream', self::ZIP));
    }

    public function testTheListOfExtensionsIsTheOneOfTheFeature(): void
    {
        self::assertSame(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'zip', 'txt'], self::sorted(PageDocumentFormat::extensions()));
    }

    /**
     * @param list<string> $extensions
     *
     * @return list<string>
     */
    private static function sorted(array $extensions): array
    {
        $order = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'zip', 'txt'];
        usort($extensions, static fn (string $a, string $b): int => array_search($a, $order, true) <=> array_search($b, $order, true));

        return $extensions;
    }
}
