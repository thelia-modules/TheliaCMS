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

namespace TheliaCMS\Tests\Unit\Menu;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Menu\FooterMenuThemeHook;

/**
 * The markup the hook writes into the footer of a theme.
 *
 * Read here rather than through a booted theme: what matters is the shape of
 * the list and the escaping, and both are decided in this class alone. Whether
 * the setting turns it on is covered by an integration test, since the value
 * comes from the module configuration.
 */
final class FooterMenuThemeHookTest extends TestCase
{
    private function links(array $nodes): string
    {
        $hook = new \ReflectionMethod(FooterMenuThemeHook::class, 'links');

        return $hook->invoke($this->hook(), $nodes);
    }

    private function hook(): FooterMenuThemeHook
    {
        return (new \ReflectionClass(FooterMenuThemeHook::class))->newInstanceWithoutConstructor();
    }

    private function node(string $label, ?string $url, array $children = [], bool $blank = false): array
    {
        return ['label' => $label, 'url' => $url, 'blank' => $blank, 'children' => $children];
    }

    public function testAnEntryBecomesALink(): void
    {
        self::assertSame(
            '<ul class="cms-menu__list"><li class="cms-menu__item"><a class="cms-menu__link" href="/livraison-et-retours">Livraison et retours</a></li></ul>',
            $this->links([$this->node('Livraison et retours', '/livraison-et-retours')]),
        );
    }

    public function testAnEntryWithNothingToPointAtIsLeftOut(): void
    {
        // A heading holding a branch has no URL: in a footer column it would be
        // a link to nowhere.
        self::assertSame('', $this->links([$this->node('Informations', null)]));
    }

    public function testANestedEntryIsRenderedAsALinkOfItsOwn(): void
    {
        $markup = $this->links([
            $this->node('Informations', null, [$this->node('Retours', '/retours')]),
        ]);

        self::assertStringContainsString('href="/retours"', $markup);
        self::assertStringNotContainsString('Informations', $markup, 'A label with no URL is not written as text in the footer.');
    }

    public function testALabelAndAnAddressAreEscaped(): void
    {
        $markup = $this->links([$this->node('Livraison & retours', '/a?b=1&c="x"')]);

        self::assertStringContainsString('Livraison &amp; retours', $markup);
        self::assertStringContainsString('href="/a?b=1&amp;c=&quot;x&quot;"', $markup);
    }

    public function testAnEntryOpeningElsewhereSaysSo(): void
    {
        $markup = $this->links([$this->node('Notre blog', 'https://example.test', [], true)]);

        // Without rel=noopener the opened page can reach back into this one.
        self::assertStringContainsString('target="_blank" rel="noopener"', $markup);
    }

    public function testAnEmptyMenuWritesNothing(): void
    {
        self::assertSame('', $this->links([]));
    }
}
