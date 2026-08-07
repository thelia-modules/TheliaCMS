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
 * The list of editor plugins is rebuilt from scratch by the module: the method
 * it overrides assigns the list rather than appending to it, so a plugin left
 * out of it is simply off, with nothing to say so.
 *
 * Two of them are read here because their absence is silent and expensive:
 * free HTML would come back on for everyone, and the blocks of the webpage
 * preset would go back to opening on a level 1 heading, which competes with the
 * title of the page and is reported at publication as a problem the author did
 * not cause.
 */
final class EditorPluginListTest extends TestCase
{
    private const string CONTROLLER_FILE = __DIR__.'/../../../assets/src/cms_page_builder_controller.js';

    public function testTheHeadingLevelsAreFixedAfterThePresetAddsItsBlocks(): void
    {
        $list = $this->pluginList();
        $preset = strpos($list, '"grapesjs:preset-webpage"');
        $headings = strpos($list, '"cms:heading-levels"');

        self::assertNotFalse($preset, 'The webpage preset is expected in the plugin list.');
        self::assertNotFalse($headings, 'Without this plugin the "Text section" block opens on an h1.');
        self::assertGreaterThan($preset, $headings, 'The plugin rewrites blocks the preset adds, so it runs after it.');
    }

    public function testFreeHtmlStaysBehindTheResource(): void
    {
        self::assertMatchesRegularExpression(
            '#if \(this\.allowCustomCodeValue\) \{\s*plugins\.push\("grapesjs:custom-code"\);#',
            $this->controllerSource(),
            'Free HTML is an authorisation: it belongs inside the check, never in the plain list.',
        );
    }

    /** The plugins turned on for every editor, in the order they are applied. */
    private function pluginList(): string
    {
        self::assertSame(
            1,
            preg_match('#const plugins = \[(.*?)\n {8}\];#s', $this->controllerSource(), $matches),
            'The plugin list is expected to be a literal, so its order can be read.',
        );

        return $matches[1];
    }

    private function controllerSource(): string
    {
        $source = file_get_contents(self::CONTROLLER_FILE);

        self::assertIsString($source, 'The editor controller is expected to be readable.');

        return $source;
    }
}
