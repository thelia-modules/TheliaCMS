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

namespace TheliaCMS\Tests\Integration\Page;

use Symfony\Component\Console\Tester\CommandTester;
use TheliaCMS\Install\LegalPageTemplates;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Page\PublishPagesCommand;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * What the command has to do that writing `published_html` from a script does
 * not: run the publish pipeline, and refuse a page that would show nothing.
 */
final class PublishPagesCommandTest extends CmsIntegrationTestCase
{
    public function testPublishesADraftThroughThePipeline(): void
    {
        $page = $this->createPage('Bulk publish', published: false);
        $pageId = (int) $page->getId();
        $locale = $this->locale();

        $this->contentOf($pageId, $locale)
            ->setDraftHtml('<p><img src="/nowhere.jpg" alt="Une photo"></p>')
            ->setPublishedHtml(null)
            ->setPublishedAt(null)
            ->save();

        $output = $this->publish(['--page' => [(string) $pageId]]);

        self::assertStringContainsString('published', $output);

        $published = (string) $this->contentOf($pageId, $locale)->getPublishedHtml();

        // The pipeline ran: these attributes are added by the ImageRewriter,
        // after the sanitiser, and nothing else in the module writes them.
        self::assertStringContainsString('decoding="async"', $published);
        self::assertStringContainsString('fetchpriority="high"', $published);
        self::assertNotNull($this->contentOf($pageId, $locale)->getPublishedAt());
    }

    public function testRefusesAPageWhoseDraftShowsNothing(): void
    {
        $page = $this->createPage('Nothing to show', published: false);
        $pageId = (int) $page->getId();
        $locale = $this->locale();

        $this->contentOf($pageId, $locale)
            ->setDraftHtml('<style>.a{color:red}</style>&nbsp;')
            ->setPublishedHtml(null)
            ->setPublishedAt(null)
            ->save();

        $output = $this->publish(['--page' => [(string) $pageId]]);

        self::assertStringContainsString('nothing to show', $output);
        self::assertNull($this->contentOf($pageId, $locale)->getPublishedAt());
    }

    /**
     * Publishing everything must not put the seeded legal pages online.
     *
     * They are created as drafts holding instructions, which is a precaution
     * against a site shipping without legal pages *and* without any real text in
     * them. A command that publishes everything undid it, and the sample notices
     * ended up online and in the sitemap.
     */
    public function testPublishingEverythingLeavesTheSampleLegalTextAlone(): void
    {
        $locale = $this->locale();
        $sample = LegalPageTemplates::PAGES['legal-notice'][$locale]
            ?? LegalPageTemplates::PAGES['legal-notice']['en_US'];

        $page = $this->createPage('Legal notice', published: false);
        $pageId = (int) $page->getId();

        $this->contentOf($pageId, $locale)
            ->setDraftHtml(LegalPageTemplates::html($sample))
            ->setPublishedHtml(null)
            ->setPublishedAt(null)
            ->save();

        $output = $this->publish(['--all' => true]);

        self::assertNull(
            $this->contentOf($pageId, $locale)->getPublishedAt(),
            'A page still holding the text it was seeded with is not published by --all.',
        );
        self::assertStringContainsString('sample text', $output);
    }

    /**
     * The guard survives a trip through the editor.
     *
     * Opening a seeded page in the builder and saving it rewrites the markup, so
     * a check comparing whole documents would stop matching while the page still
     * says "replace this placeholder".
     */
    public function testTheSampleTextIsStillRecognisedAfterTheEditorRewroteTheMarkup(): void
    {
        $locale = $this->locale();
        $sample = LegalPageTemplates::PAGES['privacy-policy'][$locale]
            ?? LegalPageTemplates::PAGES['privacy-policy']['en_US'];

        $page = $this->createPage('Privacy policy', published: false);
        $pageId = (int) $page->getId();

        $this->contentOf($pageId, $locale)
            ->setDraftHtml(\sprintf(
                '<section class="cms-block"><h1>%s</h1><div><p><span>%s</span></p></div><h2>Anything</h2></section>',
                htmlspecialchars($sample['title'], \ENT_QUOTES),
                htmlspecialchars($sample['intro'], \ENT_QUOTES),
            ))
            ->setPublishedHtml(null)
            ->setPublishedAt(null)
            ->save();

        $output = $this->publish(['--page' => [(string) $pageId]]);

        self::assertStringContainsString('sample text', $output);
        self::assertNull($this->contentOf($pageId, $locale)->getPublishedAt());
    }

    /**
     * Writing the real text lifts the refusal, with no flag to remember.
     */
    public function testAPageWhoseTextWasWrittenPublishes(): void
    {
        $locale = $this->locale();

        $page = $this->createPage('Our own legal notice', published: false);
        $pageId = (int) $page->getId();

        $this->contentOf($pageId, $locale)
            ->setDraftHtml('<h1>Mentions légales</h1><h2>Éditeur du site</h2><p>OpenStudio, 12 rue de la Liberté.</p>')
            ->setPublishedHtml(null)
            ->setPublishedAt(null)
            ->save();

        $this->publish(['--page' => [(string) $pageId]]);

        self::assertNotNull($this->contentOf($pageId, $locale)->getPublishedAt());
    }

    /**
     * A dry run announces the count a real run reaches.
     *
     * Listing a page it would then refuse is how the sample legal pages were
     * believed to be publishable in the first place.
     */
    public function testADryRunReportsWhatWouldBeRefused(): void
    {
        $locale = $this->locale();
        $sample = LegalPageTemplates::PAGES['cookies'][$locale]
            ?? LegalPageTemplates::PAGES['cookies']['en_US'];

        $page = $this->createPage('Cookies', published: false);
        $pageId = (int) $page->getId();

        $this->contentOf($pageId, $locale)
            ->setDraftHtml(LegalPageTemplates::html($sample))
            ->setPublishedHtml(null)
            ->setPublishedAt(null)
            ->save();

        $output = $this->publish(['--page' => [(string) $pageId], '--dry-run' => true]);

        self::assertStringContainsString('sample text', $output);
        self::assertStringContainsString('0 page/locale pair(s) would be published', $output);
    }

    public function testDryRunPublishesNothing(): void
    {
        $page = $this->createPage('Left alone', published: false);
        $pageId = (int) $page->getId();
        $locale = $this->locale();

        $this->contentOf($pageId, $locale)->setPublishedHtml(null)->setPublishedAt(null)->save();

        $this->publish(['--page' => [(string) $pageId], '--dry-run' => true]);

        self::assertNull($this->contentOf($pageId, $locale)->getPublishedAt());
    }

    public function testAsksForAPageOrForAllOfThem(): void
    {
        $tester = new CommandTester($this->getService(PublishPagesCommand::class));

        self::assertSame(2, $tester->execute([]));
        self::assertStringContainsString('--all', $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function publish(array $arguments): string
    {
        $command = $this->getService(PublishPagesCommand::class);
        $tester = new CommandTester($command);
        $tester->execute($arguments, ['capture_stderr_separately' => false]);

        return $tester->getDisplay();
    }

    private function contentOf(int $pageId, string $locale): \TheliaCMS\Model\CmsPageContent
    {
        $content = CmsPageContentQuery::create()->filterByPageId($pageId)->filterByLocale($locale)->findOne();

        self::assertNotNull($content, 'The page has no content row to publish.');

        return $content;
    }
}
