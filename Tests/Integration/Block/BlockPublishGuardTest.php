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

namespace TheliaCMS\Tests\Integration\Block;

use TheliaCMS\Block\Admin\CmsBlockWriter;
use TheliaCMS\Block\Admin\EmptyBlockContentException;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Model\CmsBlockContent;
use TheliaCMS\Model\CmsBlockContentQuery;
use TheliaCMS\Page\Admin\BuilderContent;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * A reusable block published empty does not answer 404 the way a page does: it
 * leaves a hole in every page showing it, on pages nobody is editing at that
 * moment. The guard therefore belongs to the writer, like the one on pages.
 */
final class BlockPublishGuardTest extends CmsIntegrationTestCase
{
    public function testABlockWithNothingInItIsNotPublished(): void
    {
        $block = $this->blockWithoutContent('bandeau-vide');

        $this->expectException(EmptyBlockContentException::class);

        try {
            $this->blockWriter()->publish($block, $this->locale());
        } finally {
            $content = $this->contentOf($block);

            // Were the date written without the HTML, the back office would
            // read "published" and the pages using the block would show a gap.
            self::assertNull($content?->getPublishedAt());
            self::assertNull($content?->getPublishedHtml());
        }
    }

    public function testABlockHoldingOnlyABlankParagraphIsNotPublished(): void
    {
        $block = $this->blockWithoutContent('bandeau-blanc');

        $this->blockWriter()->saveContent($block, $this->locale(), new BuilderContent(
            projectData: '{"pages":[]}',
            html: '<div class="cms-page-content"><p>&nbsp;</p></div>',
            css: '',
        ));

        $this->expectException(EmptyBlockContentException::class);

        $this->blockWriter()->publish($block, $this->locale());
    }

    public function testABlockWithContentIsPublished(): void
    {
        $block = $this->blockWithoutContent('bandeau-contact');

        $this->blockWriter()->saveContent($block, $this->locale(), new BuilderContent(
            projectData: '{"pages":[]}',
            html: '<p>Parlons de votre projet.</p>',
            css: '',
        ));

        $this->blockWriter()->publish($block, $this->locale());

        $content = $this->contentOf($block);

        self::assertNotNull($content?->getPublishedAt());
        self::assertStringContainsString('Parlons de votre projet.', (string) $content?->getPublishedHtml());
    }

    private function blockWriter(): CmsBlockWriter
    {
        return $this->getService(CmsBlockWriter::class);
    }

    private function blockWithoutContent(string $code): CmsBlock
    {
        $block = new CmsBlock();

        $this->blockWriter()->save($block, $this->locale(), $code, 'Bandeau');

        return $block;
    }

    private function contentOf(CmsBlock $block): ?CmsBlockContent
    {
        return CmsBlockContentQuery::create()
            ->filterByBlockId($block->getId())
            ->filterByLocale($this->locale())
            ->findOne();
    }
}
