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

namespace TheliaCMS\Storage;

/**
 * The characters the database of a Thelia site cannot be handed as they are.
 *
 * Thelia opens its Propel connection with `SET NAMES 'UTF8'`, which MariaDB and
 * MySQL read as `utf8mb3`: three bytes per character. Every character outside the
 * Basic Multilingual Plane needs four, and that is where every emoji lives. The
 * character set of the connection is decided before the one of the column, so a
 * column declared `utf8mb4` does not help: the statement is refused with
 * `Incorrect string value` (or, on a server without strict mode, silently
 * truncated, which is worse).
 *
 * The connection belongs to the framework, not to this module
 * (`core/lib/Thelia/Config/DatabaseConfigurationSource.php`), so the module makes
 * the case work on its own side instead: what it stores is always three-byte
 * safe, and what it stores is what a browser renders as the character somebody
 * typed. A numeric character reference does both, and costs nothing to read back
 * since it is plain ASCII in either direction.
 *
 * Static on purpose: the one place this has to run is `preSave()` on a Propel
 * model, where nothing is injected.
 */
final readonly class SupplementaryCharacters
{
    /**
     * The bytes a four-byte UTF-8 sequence can start with.
     *
     * Checked first because it is a single scan of the string in C, and because
     * on any real site the answer is no: a page holds a hundred kilobytes of
     * markup and the work below must not run over it for nothing.
     */
    private const string LEADING_BYTES = "\xF0\xF1\xF2\xF3\xF4";

    public static function areIn(?string $text): bool
    {
        return null !== $text
            && false !== strpbrk($text, self::LEADING_BYTES)
            && 1 === preg_match('/[\x{10000}-\x{10FFFF}]/u', $text);
    }

    /**
     * The first such character, to name it in a message an editor reads.
     */
    public static function firstIn(?string $text): ?string
    {
        if (!self::areIn($text)) {
            return null;
        }

        preg_match('/[\x{10000}-\x{10FFFF}]/u', (string) $text, $found);

        return $found[0] ?? null;
    }

    /**
     * `📷` becomes `&#128247;`, which a browser renders as `📷`.
     *
     * This is the form WordPress stores emoji in, for the same reason.
     */
    public static function toNumericReferences(?string $text): ?string
    {
        return self::replace($text, static fn (int $codePoint): string => '&#'.$codePoint.';');
    }

    /**
     * `📷` becomes `\01f4f7`, which is how a stylesheet spells it.
     *
     * A numeric character reference would not do here: `<style>` is a raw text
     * element, so the HTML parser never looks inside it and `content: "&#128247;"`
     * displays those nine characters. Six hexadecimal digits are written out in
     * full because that ends a CSS escape on its own, without the trailing space
     * a shorter one would need.
     */
    public static function toCssEscapes(?string $text): ?string
    {
        return self::replace($text, static fn (int $codePoint): string => \sprintf('\\%06x', $codePoint));
    }

    /**
     * `&#128247;` becomes `📷` again, on the way out of the database.
     *
     * The pair matters as much as the encoding: a title, a menu label or a form
     * field is handed to a template that escapes it, and the one string that
     * escapes into `📷` is `📷`. Without this, every screen showing a title would
     * read `Nos studios &#128247;`, which looks exactly like a bug.
     *
     * Only references above the plane the connection can carry are read back.
     * `&#39;` and `&amp;` are left alone: they are markup an author wrote, and
     * turning them into characters would change what a page says.
     */
    public static function fromNumericReferences(?string $text): ?string
    {
        if (null === $text || !str_contains($text, '&#')) {
            return $text;
        }

        $read = preg_replace_callback(
            '/&#(?:[xX]([0-9a-fA-F]{5,6})|(\d{6,7}));/',
            static function (array $match): string {
                $codePoint = '' !== ($match[1] ?? '') ? (int) hexdec($match[1]) : (int) $match[2];

                if ($codePoint < 0x10000 || $codePoint > 0x10FFFF) {
                    return $match[0];
                }

                return mb_chr($codePoint, 'UTF-8');
            },
            $text,
        );

        return $read ?? $text;
    }

    /**
     * @param callable(int): string $spell
     */
    private static function replace(?string $text, callable $spell): ?string
    {
        if (!self::areIn($text)) {
            return $text;
        }

        $replaced = preg_replace_callback(
            '/[\x{10000}-\x{10FFFF}]/u',
            static fn (array $match): string => $spell((int) mb_ord($match[0], 'UTF-8')),
            (string) $text,
        );

        // Handed back untouched rather than emptied when the subject is not valid
        // UTF-8: the write then fails as it does today, which is a loud problem,
        // where a silently blanked page is not.
        return $replaced ?? $text;
    }
}
