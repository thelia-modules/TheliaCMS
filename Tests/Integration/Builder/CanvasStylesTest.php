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

namespace TheliaCMS\Tests\Integration\Builder;

use TheliaCMS\Builder\CmsBuilderConfig;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * The stylesheets the canvas of the editor is handed, read from the options the
 * service really builds.
 *
 * An editor is only useful if the canvas looks like the published page. With the
 * socle of the block catalogue missing from this list, the canvas showed a stack
 * of unstyled paragraphs while the page came out laid out, and nothing in the
 * screen said why.
 */
final class CanvasStylesTest extends CmsIntegrationTestCase
{
    public function testTheCanvasIsHandedTheStylesheetOfTheBlockCatalogue(): void
    {
        $styles = $this->canvasStyles();

        self::assertNotSame([], $styles, 'The canvas loads no stylesheet at all.');
        self::assertStringContainsString('/cms/blocks.css', implode(' ', $styles));
    }

    public function testTheStylesheetCarriesAVersionSoAChangeReachesTheEditor(): void
    {
        $socle = array_values(array_filter(
            $this->canvasStyles(),
            static fn (string $url): bool => str_contains($url, '/cms/blocks.css'),
        ));

        self::assertMatchesRegularExpression('#/cms/blocks\.css\?v=[0-9a-f]{12}#', $socle[0] ?? '');
    }

    /**
     * @return list<string>
     */
    private function canvasStyles(): array
    {
        $options = $this->getService(CmsBuilderConfig::class)->editorOptions();

        return array_values($options['canvas']['styles'] ?? []);
    }
}
