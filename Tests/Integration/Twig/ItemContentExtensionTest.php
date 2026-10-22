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

namespace TheliaCMS\Tests\Integration\Twig;

use TheliaCMS\Catalogue\Admin\ItemContentWriter;
use TheliaCMS\Catalogue\CatalogueItemType;
use TheliaCMS\Content\Document;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\Twig\ItemContentExtension;
use Twig\Environment;

/**
 * What a theme prints with cms_item_content(): the published document of the
 * item, and nothing at all otherwise.
 */
final class ItemContentExtensionTest extends CmsIntegrationTestCase
{
    private const int PRODUCT_ID = 876543;

    public function testThePublishedDocumentIsPrintedAndTheDraftIsNot(): void
    {
        $writer = $this->getService(ItemContentWriter::class);
        $writer->saveContent(CatalogueItemType::Product, self::PRODUCT_ID, $this->locale(), Document::richText('<h2>Questions</h2><p>En ligne.</p>'));

        self::assertSame('', $this->render("{{ cms_item_content('product', id) }}"), 'A draft never reaches the front.');

        $writer->publish(CatalogueItemType::Product, self::PRODUCT_ID, $this->locale());
        $writer->saveContent(CatalogueItemType::Product, self::PRODUCT_ID, $this->locale(), Document::richText('<h2>Questions</h2><p>Pas encore.</p>'));

        $printed = $this->render("{{ cms_item_content('product', id) }}");

        self::assertStringContainsString('<p>En ligne.</p>', $printed, 'Printed as markup, not escaped.');
        self::assertStringContainsString('cms-page-content', $printed, 'In the container the styles of the site are scoped to.');
        self::assertStringNotContainsString('Pas encore.', $printed, 'The draft saved since stays in the back office.');
    }

    public function testAnythingElsePrintsNothing(): void
    {
        self::assertSame('', $this->render("{{ cms_item_content('product', id) }}"), 'No document.');
        self::assertSame('', $this->render("{{ cms_item_content('brand', id) }}"), 'A type that carries no document.');
        self::assertSame('', $this->render("{{ cms_item_content('product', null) }}"), 'No item.');
    }

    private function render(string $template): string
    {
        $twig = $this->getService(Environment::class);
        \assert($twig instanceof Environment);

        self::assertNotNull($twig->getExtension(ItemContentExtension::class));

        return $twig->createTemplate($template)->render(['id' => self::PRODUCT_ID]);
    }
}
