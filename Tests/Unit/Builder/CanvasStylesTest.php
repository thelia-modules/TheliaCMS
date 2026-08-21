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

/**
 * What the canvas of the editor loads.
 *
 * The point of a visual editor is that what an author builds looks like what
 * visitors will read. The canvas is an iframe with its own stylesheets, so
 * every sheet the front loads has to be named here too: with the socle of the
 * block catalogue missing, the canvas showed a stack of unstyled paragraphs
 * while the published page came out laid out.
 *
 * Read from the source because the failure is an absence, and an absence in a
 * list built at runtime has nothing to assert against once it is gone.
 */
final class CanvasStylesTest extends TestCase
{
    private const string CONFIG_FILE = __DIR__.'/../../../Builder/CmsBuilderConfig.php';

    private function canvasStyles(): string
    {
        $source = (string) file_get_contents(self::CONFIG_FILE);
        $start = strpos($source, "'canvas' => [");

        self::assertNotFalse($start, 'The canvas no longer declares its stylesheets.');

        return substr($source, $start, 900);
    }

    public function testTheCanvasLoadsTheStylesheetOfTheBlockCatalogue(): void
    {
        self::assertStringContainsString('cms.block_styles', $this->canvasStyles());
    }

    public function testTheCanvasLoadsTheStylesheetOfTheTheme(): void
    {
        self::assertStringContainsString('themeStylesheet()', $this->canvasStyles());
    }

    public function testTheCanvasLoadsTheStylesConfiguredForTheSite(): void
    {
        self::assertStringContainsString('cms.site_styles', $this->canvasStyles());
    }
}
