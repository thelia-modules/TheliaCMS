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

namespace TheliaCMS\Tests\Unit\Storage;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Storage\SupplementaryCharacters;

/**
 * Spelling out the characters a Thelia database connection cannot carry.
 *
 * The positive cases come first: they are the ones that prove the instrument
 * measures anything at all, since everything else here asserts that a string is
 * left exactly as it was.
 */
final class SupplementaryCharactersTest extends TestCase
{
    public function testAnEmojiIsWrittenAsANumericReference(): void
    {
        self::assertSame(
            '<p>Bonjour &#128247; monde</p>',
            SupplementaryCharacters::toNumericReferences("<p>Bonjour \u{1F4F7} monde</p>"),
        );
    }

    public function testEveryEmojiOfAStringIsWrittenOut(): void
    {
        self::assertSame(
            '&#128512;&#128513; ok &#127757;',
            SupplementaryCharacters::toNumericReferences("\u{1F600}\u{1F601} ok \u{1F30D}"),
        );
    }

    /**
     * The emoji sequences a keyboard actually produces are more than one
     * character: a flag is two regional indicators, a family is several people
     * joined by a zero-width joiner. Each part is written out, and the browser
     * puts the sequence back together.
     */
    public function testAnEmojiMadeOfSeveralCharactersKeepsAllOfThem(): void
    {
        self::assertSame(
            '&#127467;&#127479;',
            SupplementaryCharacters::toNumericReferences("\u{1F1EB}\u{1F1F7}"),
        );
    }

    public function testWhatAStylesheetGetsIsACssEscape(): void
    {
        self::assertSame(
            'p::after { content: "\\01f4f7"; }',
            SupplementaryCharacters::toCssEscapes("p::after { content: \"\u{1F4F7}\"; }"),
        );
    }

    /**
     * Characters inside the plane the connection can carry are left alone,
     * accented letters and the box-drawing sort of symbol included: rewriting
     * them would change every page of every site for nothing.
     */
    public function testTextTheConnectionCanCarryIsUntouched(): void
    {
        $text = '<p>Été à Nîmes — 5 €, ☎ 01 23, ✓ fait, 中文</p>';

        self::assertFalse(SupplementaryCharacters::areIn($text));
        self::assertSame($text, SupplementaryCharacters::toNumericReferences($text));
        self::assertSame($text, SupplementaryCharacters::toCssEscapes($text));
    }

    public function testNothingIsMadeOfNothing(): void
    {
        self::assertNull(SupplementaryCharacters::toNumericReferences(null));
        self::assertSame('', SupplementaryCharacters::toNumericReferences(''));
        self::assertFalse(SupplementaryCharacters::areIn(null));
    }

    public function testTheCharacterFoundIsNamedSoAMessageCanShowIt(): void
    {
        self::assertSame("\u{1F4F7}", SupplementaryCharacters::firstIn("Nos services \u{1F4F7} 2026"));
        self::assertNull(SupplementaryCharacters::firstIn('Nos services 2026'));
    }

    /**
     * A reference already written out is left as it is, so saving the same page
     * again does not pile escapes on top of each other.
     */
    public function testWritingOutWhatIsAlreadyWrittenOutChangesNothing(): void
    {
        $once = SupplementaryCharacters::toNumericReferences("Bonjour \u{1F4F7}");

        self::assertSame($once, SupplementaryCharacters::toNumericReferences($once));
    }

    public function testAReferenceIsReadBackAsTheCharacter(): void
    {
        self::assertSame(
            "Nos studios \u{1F4F7}",
            SupplementaryCharacters::fromNumericReferences('Nos studios &#128247;'),
        );
        self::assertSame(
            "\u{1F4F7}",
            SupplementaryCharacters::fromNumericReferences('&#x1F4F7;'),
        );
    }

    /**
     * What an editor typed as markup stays markup.
     *
     * `&#39;` and `&amp;` are what somebody wrote in a page, not something this
     * class put there, and turning them into characters would change what the
     * page says. Only the references above the plane the connection can carry
     * are ours.
     */
    public function testMarkupAnAuthorWroteIsNotReadBack(): void
    {
        $text = '<p>l&#39;équipe &amp; l&#39;agence &#8212; &#x2014; &copy;</p>';

        self::assertSame($text, SupplementaryCharacters::fromNumericReferences($text));
    }

    public function testWhatIsWrittenOutIsReadBackIdentical(): void
    {
        $typed = "<h1>Nos studios \u{1F4F7}</h1><p>Photo \u{1F600} et drapeau \u{1F1EB}\u{1F1F7}</p>";

        self::assertSame(
            $typed,
            SupplementaryCharacters::fromNumericReferences(SupplementaryCharacters::toNumericReferences($typed)),
        );
    }

    /**
     * A string that is not valid UTF-8 is handed back untouched rather than
     * emptied: the write then fails the way it does today, which somebody sees,
     * where a blanked page goes unnoticed.
     */
    public function testAStringThatIsNotValidTextIsHandedBackAsItIs(): void
    {
        $broken = "ok \xF0\x9F ok";

        self::assertSame($broken, SupplementaryCharacters::toNumericReferences($broken));
    }
}
