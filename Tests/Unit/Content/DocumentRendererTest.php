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

namespace TheliaCMS\Tests\Unit\Content;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Content\Document;
use TheliaCMS\Content\DocumentRenderer;
use TheliaCMS\Content\RichTextSection;
use TheliaCMS\Content\Section;
use TheliaCMS\Content\SectionCatalog;
use TheliaCMS\Content\UnknownSectionException;

final class DocumentRendererTest extends TestCase
{
    private DocumentRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new DocumentRenderer(new SectionCatalog([new RichTextSection(), new FakeSection()]));
    }

    public function testSectionsAreRenderedInOrderInsideTheContainer(): void
    {
        $document = new Document([
            new Section('title', FakeSection::NAME, ['title' => 'Nos services', 'level' => 1]),
            new Section('intro', RichTextSection::NAME, ['html' => '<p>Texte</p>']),
        ]);

        self::assertSame(
            '<div class="cms-page-content"><h1 lang="fr_FR">Nos services</h1>'."\n".'<div class="cms-richtext"><p>Texte</p></div></div>',
            $this->renderer->render($document, 'fr_FR'),
        );
    }

    public function testAnEmptyDocumentRendersAnEmptyContainer(): void
    {
        self::assertSame('<div class="cms-page-content"></div>', $this->renderer->render(new Document([]), 'fr_FR'));
    }

    public function testASectionWhoseComponentIsGoneIsAnErrorNotAHole(): void
    {
        $this->expectException(UnknownSectionException::class);

        $this->renderer->render(new Document([new Section('a', 'Gone:Component', [])]), 'fr_FR');
    }
}
