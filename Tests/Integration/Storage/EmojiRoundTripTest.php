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

namespace TheliaCMS\Tests\Integration\Storage;

use Propel\Runtime\Propel;
use TheliaCMS\Block\Admin\CmsBlockWriter;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Model\CmsBlockContentQuery;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Model\CmsPageRevisionQuery;
use TheliaCMS\Page\Admin\BuilderContent;
use TheliaCMS\Page\Admin\PageDraft;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * An emoji typed into a page, from the editor to the database and back.
 *
 * Thelia opens its connection on `SET NAMES 'UTF8'`, which the server reads as
 * three bytes per character, so a statement carrying an emoji is refused with
 * `Incorrect string value` however the column is declared. Every write of the
 * module goes through the same Propel hook, which spells the character out before
 * it reaches the statement.
 */
final class EmojiRoundTripTest extends CmsIntegrationTestCase
{
    private const string EMOJI = "\u{1F4F7}";

    /**
     * The premise of everything below: the connection really is the narrow one.
     *
     * Asserted rather than assumed, so that the day the framework opens the
     * connection on `utf8mb4` this suite says so instead of quietly testing a
     * workaround nobody needs any more.
     */
    public function testTheConnectionOfTheSiteIsTheOneThatCannotCarryAnEmoji(): void
    {
        $charset = (string) Propel::getConnection('TheliaMain')
            ->query("SHOW VARIABLES LIKE 'character_set_connection'")
            ->fetchColumn(1);

        if ('utf8mb4' === $charset) {
            self::markTestSkipped('The connection carries four-byte characters on its own, so nothing here has anything to measure.');
        }

        self::assertSame('utf8mb3', $charset);
    }

    public function testAPageContainingAnEmojiCanBeSavedAndPublished(): void
    {
        $page = $this->createPage(
            'Page avec émotion',
            html: '<h1>Nos services</h1><p>Photo '.self::EMOJI.' du studio</p>',
        );

        $content = CmsPageContentQuery::create()
            ->filterByPageId($page->getId())
            ->filterByLocale($this->locale())
            ->findOne();

        self::assertNotNull($content);
        self::assertStringContainsString(
            self::EMOJI,
            (string) $content->getPublishedHtml(),
            'What comes back out of the database is the character that was typed.',
        );
    }

    /**
     * Reading the row back through anything but Propel, so the assertion is about
     * the bytes in the table and not about what a model does with them.
     */
    public function testWhatIsInTheTableIsPlainAscii(): void
    {
        $page = $this->createPage('Page mesurée', html: '<h1>Titre</h1><p>'.self::EMOJI.'</p>');

        $stored = (string) Propel::getConnection('TheliaMain')
            ->query(\sprintf('SELECT published_html FROM cms_page_content WHERE page_id = %d', (int) $page->getId()))
            ->fetchColumn();

        self::assertStringContainsString('&#128247;', $stored);
        self::assertSame($stored, mb_convert_encoding($stored, 'ASCII', 'UTF-8'), 'Nothing outside ASCII is left in the column.');
    }

    /**
     * The draft, saved every thirty seconds by the editor, is written by the same
     * path and was refused by the same statement.
     */
    public function testADraftContainingAnEmojiIsSaved(): void
    {
        $page = $this->createPage('Brouillon', published: false);

        $this->writer()->saveContent($page, $this->locale(), new BuilderContent(
            projectData: '{"pages":[{"note":"'.self::EMOJI.'"}]}',
            html: '<h1>Brouillon</h1><p>'.self::EMOJI.'</p>',
            css: '.note::after { content: "'.self::EMOJI.'"; }',
        ));

        $content = CmsPageContentQuery::create()
            ->filterByPageId($page->getId())
            ->filterByLocale($this->locale())
            ->findOne();

        self::assertNotNull($content);
        self::assertStringContainsString(self::EMOJI, (string) $content->getDraftHtml());
        self::assertStringContainsString(
            '\\01f4f7',
            (string) $content->getDraftCss(),
            'A stylesheet keeps the escape a stylesheet understands, not the markup one.',
        );
        self::assertStringContainsString(
            self::EMOJI,
            (string) $content->getDraftProjectData(),
            'The project the editor reloads holds it too, or the character is lost on the next open.',
        );
    }

    /**
     * The snapshot taken at every publication holds a copy of the same content.
     */
    public function testTheRevisionTakenAtPublicationHoldsIt(): void
    {
        $page = $this->createPage('Page avec révision', html: '<h1>Titre</h1><p>'.self::EMOJI.'</p>');

        $revision = CmsPageRevisionQuery::create()
            ->filterByPageId($page->getId())
            ->findOne();

        self::assertNotNull($revision);
        self::assertStringContainsString(self::EMOJI, (string) $revision->getHtml());
    }

    /**
     * A title reaches a template that escapes it, so it has to come back out of
     * the database as the character: escaping the stored reference would put
     * `Nos studios &#128247;` on every screen the title appears on.
     */
    public function testATitleContainingAnEmojiComesBackAsTheEmoji(): void
    {
        $page = $this->createPage('Nos services', published: false);

        $this->writer()->saveDraft($page, $this->locale(), new PageDraft(
            title: 'Nos services '.self::EMOJI,
            slug: 'nos-services',
        ));

        // Read from the database rather than from the object the writer just
        // handed back, which still holds what was typed either way.
        $reloaded = CmsPageQuery::create()->findPk($page->getId());

        self::assertNotNull($reloaded);
        self::assertSame('Nos services '.self::EMOJI, $reloaded->setLocale($this->locale())->getTitle());
    }

    /**
     * A reusable block is written by its own writer, on its own tables.
     */
    public function testAReusableBlockContainingAnEmojiIsSaved(): void
    {
        $writer = $this->getService(CmsBlockWriter::class);
        $locale = $this->locale();

        $block = new CmsBlock();
        $writer->save($block, $locale, 'bandeau-emoji', 'Bandeau');

        $writer->saveContent($block, $locale, new BuilderContent(
            projectData: '{"pages":[]}',
            html: '<p>Suivez-nous '.self::EMOJI.'</p>',
            css: '',
        ));
        $writer->publish($block, $locale);

        $content = CmsBlockContentQuery::create()
            ->filterByBlockId($block->getId())
            ->filterByLocale($locale)
            ->findOne();

        self::assertNotNull($content);
        self::assertStringContainsString(self::EMOJI, (string) $content->getPublishedHtml());
    }
}
