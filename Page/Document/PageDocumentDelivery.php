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
 * How a stored document is handed to a visitor: the type it is served as and
 * whether the browser shows it or saves it.
 *
 * Both come from the extension, which the storage guarantees (a stored name
 * only exists with one of the accepted extensions), never from the type
 * recorded at upload. A file served from the address of the site under a type
 * it was not given is a page of the site for a browser that sniffs it.
 */
final class PageDocumentDelivery
{
    /** What a browser renders on its own, without a program to open the file. */
    private const array INLINE = ['pdf', 'txt'];

    private function __construct()
    {
    }

    /**
     * The type the file is served as, null for an extension no document has.
     */
    public static function contentType(string $extension): ?string
    {
        $extension = strtolower($extension);

        // Plain text declares its charset: without one a browser guesses it from
        // the bytes, and a note written in UTF-8 reads as garbage.
        if ('txt' === $extension) {
            return 'text/plain; charset=UTF-8';
        }

        // The first type of each extension is its own; the others are what
        // libmagic may answer of the same file at upload.
        return PageDocumentFormat::ACCEPTED_MIME_TYPES[$extension][0] ?? null;
    }

    public static function isInline(string $extension): bool
    {
        return \in_array(strtolower($extension), self::INLINE, true);
    }
}
