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

namespace TheliaCMS\Tests\Integration\ImportExport;

use Propel\Runtime\ActiveQuery\Criteria;
use TheliaCMS\ImportExport\ExportFormat;
use TheliaCMS\ImportExport\ImportOptions;
use TheliaCMS\ImportExport\ImportReport;
use TheliaCMS\ImportExport\SiteDocument;
use TheliaCMS\ImportExport\SiteImporter;
use TheliaCMS\Model\CmsBlockContentQuery;
use TheliaCMS\Model\CmsBlockQuery;
use TheliaCMS\Model\CmsFormQuery;
use TheliaCMS\Model\CmsPageContent;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageI18nQuery;
use TheliaCMS\Model\CmsPageSearchQuery;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * An export file is not a trusted author: whoever can hand one to the import
 * command would otherwise put on the front what no back-office account may
 * publish. What it carries goes through the checks of the screens.
 */
final class HostileImportTest extends CmsIntegrationTestCase
{
    private const string HOSTILE = '<p>Bonjour</p><script>alert(1)</script><img src="/x.png" onerror="alert(2)" alt=""><a href="javascript:alert(3)">lien</a>';

    private const string EMBED = '<iframe src="https://www.youtube.com/embed/abc"></iframe>';

    private const string REMOTE_CSS = '.hero { background: url(https://tracker.example/pixel.png); }';

    public function testTheMarkupOfAPageIsFilteredLikeAPublication(): void
    {
        $report = $this->import(['pages' => [$this->page('Page hostile', self::HOSTILE.self::EMBED)]]);

        $content = $this->contentOf('Page hostile');

        foreach (['draft' => $content->getDraftHtml(), 'published' => $content->getPublishedHtml()] as $state => $html) {
            self::assertStringContainsString('<p>Bonjour</p>', (string) $html, \sprintf('The %s keeps what a page may hold.', $state));
            self::assertStringNotContainsString('<script', (string) $html, \sprintf('The %s loses its script.', $state));
            self::assertStringNotContainsString('onerror', (string) $html);
            self::assertStringNotContainsString('javascript:', (string) $html);
            self::assertStringNotContainsString('<iframe', (string) $html, 'Custom code needs the import to allow it.');
        }

        foreach (['draft' => $content->getDraftCss(), 'published' => $content->getPublishedCss()] as $state => $css) {
            self::assertStringNotContainsString('tracker.example', (string) $css, \sprintf('The %s stylesheet calls no other host.', $state));
        }

        self::assertStringNotContainsString('alert(1)', (string) CmsPageSearchQuery::create()->filterByPageId($content->getPageId())->findOne()?->getContent(), 'The search index is built from the filtered markup.');
        $this->assertWarned($report, 'Page hostile', 'filtered', 'custom code');
        $this->assertWarned($report, 'Page hostile', 'stylesheet');
    }

    public function testCustomCodeComesInWhenTheImportAllowsIt(): void
    {
        $report = $this->import(['pages' => [$this->page('Page avec vidéo', self::HOSTILE.self::EMBED)]], new ImportOptions(allowCustomCode: true));

        $html = (string) $this->contentOf('Page avec vidéo')->getPublishedHtml();

        self::assertStringContainsString('<iframe', $html, 'Allowed, the embed is kept.');
        self::assertStringNotContainsString('<script', $html, 'A script never is.');
        $this->assertNotWarned($report, 'custom code');
    }

    public function testTheMarkupOfABlockIsFilteredToo(): void
    {
        $report = $this->import(['blocks' => [[
            'code' => 'import-hostile',
            'translations' => [$this->locale() => ['title' => 'Bloc hostile']],
            'contents' => [$this->locale() => $this->contents(self::HOSTILE)],
        ]]]);

        $content = CmsBlockContentQuery::create()
            ->filterByBlockId((int) CmsBlockQuery::create()->findOneByCode('import-hostile')?->getId())
            ->filterByLocale($this->locale())
            ->findOne();

        foreach (['draft' => $content?->getDraftHtml(), 'published' => $content?->getPublishedHtml()] as $state => $html) {
            self::assertStringContainsString('<p>Bonjour</p>', (string) $html, \sprintf('The %s keeps what a block may hold.', $state));
            self::assertStringNotContainsString('<script', (string) $html, \sprintf('The %s loses its script.', $state));
            self::assertStringNotContainsString('onerror', (string) $html);
        }

        self::assertStringNotContainsString('tracker.example', (string) $content?->getPublishedCss());
        $this->assertWarned($report, 'import-hostile', 'filtered');
    }

    public function testAFormIsCheckedLikeTheFormScreenChecksIt(): void
    {
        $report = $this->import(['forms' => [
            self::form('hostile"><script>alert(1)</script>'),
            self::form('import-contact', recipients: 'ventes@example.com, pas-une-adresse, compta@example.com', retentionDays: -1),
            self::form('import-devis', retentionDays: 999999),
            self::form('import-rappel', retentionDays: 'toujours'),
        ]]);

        self::assertSame(0, CmsFormQuery::create()->filterByCode('hostile%', Criteria::LIKE)->count(), 'A code the screen refuses is not imported.');
        $this->assertWarned($report, 'hostile', 'code');

        $contact = CmsFormQuery::create()->findOneByCode('import-contact');
        self::assertSame('ventes@example.com, compta@example.com', $contact?->getRecipients(), 'Only the addresses that are addresses are kept.');
        $this->assertWarned($report, 'pas-une-adresse', 'address');
        self::assertSame(365, $contact?->getRetentionDays(), 'A negative duration, which would keep the answers forever, falls back to the default.');

        self::assertSame(3650, CmsFormQuery::create()->findOneByCode('import-devis')?->getRetentionDays(), 'The longest duration the screen accepts.');
        self::assertSame(365, CmsFormQuery::create()->findOneByCode('import-rappel')?->getRetentionDays(), 'A duration that is not a number is not zero, which keeps forever.');
        $this->assertWarned($report, 'import-devis', 'kept');
    }

    /**
     * @param array<string, mixed> $sections
     */
    private function import(array $sections, ImportOptions $options = new ImportOptions()): ImportReport
    {
        return $this->getService(SiteImporter::class)->import(SiteDocument::fromArray([
            'format' => ExportFormat::NAME,
            'version' => ExportFormat::VERSION,
            'module_version' => '1.6.0',
            'locales' => [$this->locale()],
            ...$sections,
        ]), $options);
    }

    /**
     * @return array<string, mixed>
     */
    private function page(string $title, string $html): array
    {
        return [
            'uid' => 'page-'.md5($title),
            'parent' => null,
            'position' => 0,
            'visible' => true,
            'page_type' => 'default',
            'translations' => [$this->locale() => ['title' => $title, 'slug' => 'import-'.md5($title)]],
            'contents' => [$this->locale() => $this->contents($html)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contents(string $html): array
    {
        return [
            'project_data' => '{"pages":[]}',
            'draft_html' => $html,
            'draft_css' => self::REMOTE_CSS,
            'published_html' => $html,
            'published_css' => self::REMOTE_CSS,
            'published_at' => '2026-10-01T00:00:00+00:00',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function form(string $code, ?string $recipients = 'ventes@example.com', mixed $retentionDays = 365): array
    {
        return [
            'code' => $code,
            'active' => true,
            'recipients' => $recipients,
            'store_submissions' => true,
            'retention_days' => $retentionDays,
            'translations' => [],
            'fields' => [],
        ];
    }

    private function contentOf(string $title): CmsPageContent
    {
        $pageId = (int) CmsPageI18nQuery::create()->filterByTitle($title)->findOne()?->getId();
        $content = CmsPageContentQuery::create()->filterByPageId($pageId)->filterByLocale($this->locale())->findOne();

        self::assertInstanceOf(CmsPageContent::class, $content, \sprintf('Page "%s" was imported.', $title));

        return $content;
    }

    private function assertWarned(ImportReport $report, string ...$fragments): void
    {
        foreach ($report->warnings() as $warning) {
            if ([] === array_filter($fragments, static fn (string $fragment): bool => !str_contains($warning, $fragment))) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        self::fail(\sprintf("No warning names %s:\n%s", implode(' and ', $fragments), implode("\n", $report->warnings())));
    }

    private function assertNotWarned(ImportReport $report, string $fragment): void
    {
        self::assertSame([], array_values(array_filter($report->warnings(), static fn (string $warning): bool => str_contains($warning, $fragment))));
    }
}
