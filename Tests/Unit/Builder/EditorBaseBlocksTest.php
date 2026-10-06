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
use TheliaCMS\Builder\EditorBaseBlocks;

final class EditorBaseBlocksTest extends TestCase
{
    public function testItListsTheThreeCategoriesOfThePanelInOrder(): void
    {
        $groups = (new EditorBaseBlocks($this->translator()))->groups();

        self::assertSame(['basic', 'layout', 'advanced'], array_keys($groups));

        foreach ($groups as $group) {
            self::assertNotSame([], $group['blocks']);
            self::assertStringStartsWith('back-office:', $group['label'], 'A category is a heading of the settings screen, read in the language of the back office.');
        }
    }

    /**
     * The ids are what the editor registers its blocks under: they are the
     * only thing that lets the setting reach the panel.
     */
    public function testEveryBlockHasOneIdAndTheIdsCoverEveryGroup(): void
    {
        $base = new EditorBaseBlocks($this->translator());
        $ids = $base->ids();

        self::assertSame($ids, array_values(array_unique($ids)), 'Two blocks under one id would be switched off together.');
        self::assertSame($ids, array_merge(...array_map(static fn (array $group): array => array_keys($group['blocks']), array_values($base->groups()))));

        foreach (['video', 'map', 'quote', 'text-basic', 'section', 'column2', 'accordion', 'custom-code'] as $id) {
            self::assertContains($id, $ids);
        }
    }

    public function testTheBlocksAreNamedInTheLanguageOfTheBackOffice(): void
    {
        foreach ((new EditorBaseBlocks($this->translator()))->groups() as $group) {
            foreach ($group['blocks'] as $label) {
                self::assertStringStartsWith('back-office:', $label);
            }
        }
    }

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
