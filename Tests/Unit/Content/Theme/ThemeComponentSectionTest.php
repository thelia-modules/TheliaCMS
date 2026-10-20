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
use Symfony\UX\TwigComponent\ComponentRendererInterface;
use TheliaCMS\Content\Prop;
use TheliaCMS\Content\PropSchema;
use TheliaCMS\Content\Theme\ThemeComponentSection;

/**
 * A component of the theme that takes several records gets their ids as a
 * list, the way a template of the theme would hand them over, while the
 * document stores rows an editor picks one by one.
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
}
