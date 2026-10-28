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

namespace TheliaCMS\Tests\Unit\Content\Theme;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\Translation\Translator;
use Symfony\UX\TwigComponent\ComponentRendererInterface;
use TheliaCMS\Content\Prop;
use TheliaCMS\Content\PropSchema;
use TheliaCMS\Content\Theme\ThemeComponentSection;

/**
 * A component of the theme that takes several records gets their ids as a
 * list, the way a template of the theme would hand them over, while the
 * document stores rows an editor picks one by one. It is drawn in the
 * language of the page, whoever saves it.
 */
final class ThemeComponentSectionTest extends TestCase
{
    public function testRowsOfIdsReachTheComponentAsAListOfIds(): void
    {
        $received = null;
        $renderer = $this->createStub(ComponentRendererInterface::class);
        $renderer->method('createAndRender')->willReturnCallback(static function (string $name, array $props) use (&$received): string {
            $received = [$name, $props];

            return '<div></div>';
        });
        $schema = new PropSchema([
            Prop::text('title', 'title'),
            Prop::items('product_ids', 'product_ids', [Prop::reference('id', 'id', 'product', true, null, '/sources/products', true)], 0, 12),
        ]);
        $section = new ThemeComponentSection('Layouts:CrossSelling:Base', '', $schema, ['title' => 'title', 'product_ids' => 'productIds'], $renderer, ['product_ids']);

        $section->render(['title' => 'Picked', 'product_ids' => [['id' => 22], ['id' => '17'], ['id' => 0]]], 'fr_FR');

        self::assertSame(['Layouts:CrossSelling:Base', ['title' => 'Picked', 'productIds' => [22, 17]]], $received, 'The ids, in order, under the name the component spells; no id is no row.');
        self::assertSame([['id' => 1], ['id' => 2]], $section->sample()['product_ids'], 'The example shows the shape of a row.');
    }

    public function testTheExampleOfTheThemeWinsOverTheDefaults(): void
    {
        $schema = new PropSchema([
            Prop::text('alt', 'alt', 'Default text'),
            Prop::text('embed_url', 'embed_url'),
            Prop::text('provider', 'provider'),
        ]);
        $example = ['embed_url' => 'https://www.youtube-nocookie.com/embed/abc', 'provider' => 'youtube', 'unknown' => 'dropped'];
        $section = new ThemeComponentSection('Organisms:VideoPlayer:Base', '', $schema, [], $this->createStub(ComponentRendererInterface::class), [], $example);

        self::assertSame(
            ['alt' => 'Default text', 'embed_url' => 'https://www.youtube-nocookie.com/embed/abc', 'provider' => 'youtube'],
            $section->sample(),
            'The theme fills what it gives, the defaults the rest, and a prop the component does not take is left out.',
        );
    }

    public function testTheComponentIsDrawnInTheLanguageOfThePage(): void
    {
        $translator = new Translator('en_US');
        $drawnIn = null;
        $renderer = $this->createStub(ComponentRendererInterface::class);
        $renderer->method('createAndRender')->willReturnCallback(static function () use ($translator, &$drawnIn): string {
            $drawnIn = $translator->getLocale();

            return '<div></div>';
        });
        $section = new ThemeComponentSection('Organisms:RecipeSteps:Base', '', new PropSchema([Prop::text('title', 'title')]), ['title' => 'title'], $renderer, localeSwitcher: new LocaleSwitcher('en_US', [$translator]));

        $section->render(['title' => 'Préparation'], 'fr_FR');

        self::assertSame('fr_FR', $drawnIn, 'A page in French is drawn in French, whatever the language of the one saving it.');
        self::assertSame('en_US', $translator->getLocale(), 'The language of the request is given back.');
    }
}
