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

namespace TheliaCMS\Tests\Unit\Partial;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Translation\IdentityTranslator;
use TheliaCMS\Partial\PartialDefinitionInterface;
use TheliaCMS\Partial\PartialFragmentRendererInterface;
use TheliaCMS\Partial\PartialProp;
use TheliaCMS\Partial\PartialProps;
use TheliaCMS\Partial\PartialRegistry;
use TheliaCMS\Partial\PartialRenderer;
use TheliaCMS\Partial\UnknownPartialException;

/**
 * Substituting the placeholders of a published page.
 *
 * What a page holds is a name and some settings; what a visitor gets is what
 * the server renders for them. Everything in between — an unknown name, a
 * missing setting, a template that throws — has to end with a page that is
 * still served.
 */
final class PartialRendererTest extends TestCase
{
    public function testReturnsThePageUntouchedWhenItHoldsNoBlock(): void
    {
        $html = '<div class="cms-page-content"><h1>Hello</h1></div>';

        self::assertSame($html, $this->renderer([])->substitute($html, 'fr_FR'));
    }

    public function testReplacesAPlaceholderWithWhatTheServerRenders(): void
    {
        $renderer = $this->renderer([new FakePartial('cms-menu')]);

        $html = $renderer->substitute(
            '<p>Before</p><div data-cms-partial="cms-menu" data-props="{}"></div><p>After</p>',
            'fr_FR',
        );

        self::assertStringContainsString('<nav>cms-menu</nav>', (string) $html);
        self::assertStringContainsString('<p>Before</p>', (string) $html);
        self::assertStringContainsString('<p>After</p>', (string) $html);
        // The placeholder itself is gone: a published page never ships the
        // instruction that produced it.
        self::assertStringNotContainsString('data-cms-partial', (string) $html);
    }

    public function testHandsTheStoredSettingsToTheDefinition(): void
    {
        $partial = new FakePartial('news', [PartialProp::integer('count', 'How many', 3, 1, 12)]);
        $renderer = $this->renderer([$partial]);

        $html = $renderer->substitute(
            '<div data-cms-partial="news" data-props=\'{"count": 7}\'></div>',
            'fr_FR',
        );

        self::assertStringContainsString('count=7', (string) $html);
        self::assertStringContainsString('locale=fr_FR', (string) $html);
    }

    public function testLeavesOutABlockNoModuleProvides(): void
    {
        $renderer = $this->renderer([]);

        $html = $renderer->substitute(
            '<p>Kept</p><div data-cms-partial="gone-with-its-module"></div>',
            'fr_FR',
        );

        self::assertSame('<p>Kept</p>', (string) $html);
    }

    public function testLeavesOutABlockMissingASettingItNeeds(): void
    {
        $partial = new FakePartial('cms-menu', [PartialProp::reference('menu', 'Menu', '/sources/menus')]);
        $renderer = $this->renderer([$partial]);

        $html = $renderer->substitute(
            '<p>Kept</p><div data-cms-partial="cms-menu" data-props="{}"></div>',
            'fr_FR',
        );

        self::assertSame('<p>Kept</p>', (string) $html);
    }

    public function testServesTheRestOfThePageWhenABlockThrows(): void
    {
        $renderer = new PartialRenderer(
            new PartialRegistry([new FakePartial('broken')]),
            new PartialProps(new IdentityTranslator()),
            new class implements PartialFragmentRendererInterface {
                public function render(PartialDefinitionInterface $definition, array $props, string $locale, bool $cache = true): string
                {
                    throw new \RuntimeException('the template is broken');
                }
            },
            new NullLogger(),
        );

        $html = $renderer->substitute('<p>Kept</p><div data-cms-partial="broken"></div>', 'fr_FR');

        self::assertSame('<p>Kept</p>', (string) $html);
    }

    /**
     * Props are read as JSON. A malformed attribute is no reason to stop: the
     * block renders with its defaults, which is what an editor sees the moment
     * they drop it on the page.
     */
    public function testTreatsUnreadableSettingsAsNoneAtAll(): void
    {
        $partial = new FakePartial('news', [PartialProp::integer('count', 'How many', 3, 1, 12)]);
        $renderer = $this->renderer([$partial]);

        $html = $renderer->substitute('<div data-cms-partial="news" data-props="{oops"></div>', 'fr_FR');

        self::assertStringContainsString('count=3', (string) $html);
    }

    public function testReplacesEveryPlaceholderOfThePage(): void
    {
        $renderer = $this->renderer([new FakePartial('cms-menu'), new FakePartial('news')]);

        $html = (string) $renderer->substitute(
            '<div data-cms-partial="cms-menu"></div><section><div data-cms-partial="news"></div></section>',
            'fr_FR',
        );

        self::assertStringContainsString('<nav>cms-menu</nav>', $html);
        self::assertStringContainsString('<nav>news</nav>', $html);
    }

    public function testRefusesToRenderABlockThatIsNotRegistered(): void
    {
        $this->expectException(UnknownPartialException::class);

        $this->renderer([])->renderOne('anything', [], 'fr_FR');
    }

    /**
     * @param list<PartialDefinitionInterface> $definitions
     */
    private function renderer(array $definitions): PartialRenderer
    {
        return new PartialRenderer(
            new PartialRegistry($definitions),
            new PartialProps(new IdentityTranslator()),
            new class implements PartialFragmentRendererInterface {
                public function render(PartialDefinitionInterface $definition, array $props, string $locale, bool $cache = true): string
                {
                    $settings = [];

                    foreach ($props as $name => $value) {
                        $settings[] = $name.'='.var_export($value, true);
                    }

                    return \sprintf(
                        '<nav>%s</nav><!-- %s locale=%s -->',
                        $definition->name(),
                        implode(' ', str_replace("'", '', $settings)),
                        $locale,
                    );
                }
            },
            new NullLogger(),
        );
    }
}
