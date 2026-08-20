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
use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\Builder\CoreBlockCatalog;

/**
 * Two languages meet in the block panel, and they are not the same one.
 *
 * The name of a block and of its category are read in the panel, next to the
 * categories the editor library names itself, so they follow the back office.
 * The sample text inside a block lands in the page, so it follows the language
 * the page is being written in. Getting the two from one locale is what left a
 * French panel with an English heading in the middle of it.
 */
final class CoreBlockCatalogLocaleTest extends TestCase
{
    public function testTheNameOfABlockIsReadInTheLanguageOfTheBackOffice(): void
    {
        $blocks = (new CoreBlockCatalog($this->translator()))->blocks('de_DE');

        self::assertNotSame([], $blocks);

        foreach ($blocks as $block) {
            self::assertStringStartsWith('back-office:', $block->label, 'A block is named in the panel, not in the page.');
            self::assertStringStartsWith('back-office:', $block->category, 'A category is a heading of the panel.');
        }
    }

    public function testTheSampleTextOfABlockIsWrittenInTheLanguageOfThePage(): void
    {
        $blocks = (new CoreBlockCatalog($this->translator()))->blocks('de_DE');

        $sampleText = implode('', array_map(static fn (object $block): string => $block->content, $blocks));

        self::assertStringContainsString('page-de_DE:', $sampleText, 'An editor writing the German page would get placeholder text in another language.');
        self::assertStringNotContainsString('back-office:', $sampleText, 'The panel language leaked into the page.');
    }

    /**
     * Marks each translation with the locale it was asked in, so the two paths
     * can be told apart: the back office is the call that passes none.
     */
    private function translator(): TranslatorInterface
    {
        return new class implements TranslatorInterface {
            public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return (null === $locale ? 'back-office:' : 'page-'.$locale.':').$id;
            }

            public function getLocale(): string
            {
                return 'fr_FR';
            }
        };
    }
}
