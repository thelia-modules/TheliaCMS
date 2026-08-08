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

namespace TheliaCMS\Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Builder\PlaceholderContentChecker;
use TheliaCMS\Install\LegalPageTemplates;

/**
 * Recognising the sample text a legal page is seeded with.
 *
 * The point is not to guess whether a page is finished: it is to know that
 * nobody has replaced the instructions the module wrote, so that publishing
 * cannot put them online.
 */
final class PlaceholderContentCheckerTest extends TestCase
{
    private PlaceholderContentChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new PlaceholderContentChecker();
    }

    public function testEverySeededLegalPageIsRecognised(): void
    {
        foreach (LegalPageTemplates::PAGES as $code => $translations) {
            foreach ($translations as $locale => $translation) {
                self::assertTrue(
                    $this->checker->isPlaceholder(LegalPageTemplates::html($translation)),
                    \sprintf('The seeded draft of "%s" in %s is the sample text.', $code, $locale),
                );
            }
        }
    }

    public function testAPageSomebodyWroteIsNotSampleText(): void
    {
        $html = '<h1>Mentions légales</h1><h2>Éditeur du site</h2><p>OpenStudio, SAS au capital de 100 000 euros.</p>';

        self::assertFalse($this->checker->isPlaceholder($html));
    }

    public function testAnEmptyPageIsNotSampleText(): void
    {
        self::assertFalse($this->checker->isPlaceholder(null));
        self::assertFalse($this->checker->isPlaceholder('   '));
    }

    /**
     * Half-written counts as sample text: whoever added a heading and stopped has
     * not written the page, and the instructions are still on it.
     */
    public function testAPageWhereOnlyTheMarkupChangedIsStillSampleText(): void
    {
        $intro = LegalPageTemplates::PAGES['cookies']['en_US']['intro'];
        $html = '<section><div><h1>Cookies</h1><p><em>'.$intro.'</em></p></div><h2>Our own heading</h2><p>Written.</p></section>';

        self::assertSame($intro, $this->checker->placeholderSentenceIn($html));
    }

    /**
     * A round trip through a form or a copy and paste can turn a typographic
     * apostrophe into a straight one, and it is the same sentence.
     */
    public function testTheShapeOfTheApostropheDoesNotMatter(): void
    {
        $intro = LegalPageTemplates::PAGES['legal-notice']['fr_FR']['intro'];

        self::assertStringContainsString('’', $intro, 'The French sample text is written with a typographic apostrophe.');
        self::assertTrue($this->checker->isPlaceholder('<p>'.str_replace('’', "'", $intro).'</p>'));
    }

    /**
     * The sentence survives being escaped, which is how it reaches the database.
     */
    public function testAnEscapedSentenceIsRecognised(): void
    {
        $intro = LegalPageTemplates::PAGES['privacy-policy']['fr_FR']['intro'];

        self::assertTrue($this->checker->isPlaceholder('<p>'.htmlspecialchars($intro, \ENT_QUOTES).'</p>'));
    }
}
