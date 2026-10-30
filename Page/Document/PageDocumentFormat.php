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

namespace TheliaCMS\Page\Document;

/**
 * The files a page may offer to download: the extension says what the file
 * claims to be, the type read from its bytes says whether it is.
 *
 * Both have to agree. A name alone lets an HTML page or a script through as
 * a "pdf", served from the address of the site; the bytes alone let a file
 * through under an extension a visitor's computer opens with something else.
 *
 * The types are the ones fileinfo (libmagic 5.43, bundled with PHP 8.3) gives
 * for files written by LibreOffice, TextEdit and zip, measured one by one.
 * Two answers are not the type of the format and are still accepted:
 * - `application/zip` for an Office Open XML or OpenDocument file whose
 *   entries are not in the order libmagic looks for (the `mimetype` entry of
 *   an OpenDocument file compressed, or not first), which is a zip, as those
 *   formats are;
 * - `application/octet-stream` for the same formats written as a stream (a
 *   zip with data descriptors, as a web application generating a file
 *   writes it), and only when the file starts as a zip does.
 * `application/x-ole-storage` is what libmagic says of a Word, Excel or
 * PowerPoint 97-2003 file whose application it cannot tell: the same
 * container, nothing a browser runs.
 */
final class PageDocumentFormat
{
    public const array ACCEPTED_MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/x-ole-storage'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'zip' => ['application/zip'],
        'txt' => ['text/plain'],
    ];

    /** The extensions whose file is a zip, which libmagic may not name. */
    private const array ZIP_CONTAINERS = ['docx', 'xlsx', 'pptx', 'odt', 'zip'];

    /** The four bytes a zip starts with: the signature of its first local header. */
    private const string ZIP_SIGNATURE = "PK\x03\x04";

    private function __construct()
    {
    }

    /**
     * @return list<string>
     */
    public static function extensions(): array
    {
        return array_keys(self::ACCEPTED_MIME_TYPES);
    }

    /**
     * The extension of a file name, lower case, empty when it has none.
     */
    public static function extensionOf(string $fileName): string
    {
        return strtolower(pathinfo($fileName, \PATHINFO_EXTENSION));
    }

    /**
     * @param string $leadingBytes the first bytes of the file, at least four
     */
    public static function accepts(string $extension, ?string $mimeType, string $leadingBytes): bool
    {
        $extension = strtolower($extension);

        if (!isset(self::ACCEPTED_MIME_TYPES[$extension]) || null === $mimeType) {
            return false;
        }

        if (\in_array($mimeType, self::ACCEPTED_MIME_TYPES[$extension], true)) {
            return true;
        }

        return 'application/octet-stream' === $mimeType
            && \in_array($extension, self::ZIP_CONTAINERS, true)
            && str_starts_with($leadingBytes, self::ZIP_SIGNATURE);
    }
}
