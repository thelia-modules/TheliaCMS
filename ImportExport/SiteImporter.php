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

namespace TheliaCMS\ImportExport;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Model\RewritingUrlQuery;
use TheliaCMS\Menu\MenuCache;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Model\CmsBlockContent;
use TheliaCMS\Model\CmsBlockContentQuery;
use TheliaCMS\Model\CmsBlockQuery;
use TheliaCMS\Model\CmsForm;
use TheliaCMS\Model\CmsFormField;
use TheliaCMS\Model\CmsFormFieldQuery;
use TheliaCMS\Model\CmsFormQuery;
use TheliaCMS\Model\CmsMenu;
use TheliaCMS\Model\CmsMenuItem;
use TheliaCMS\Model\CmsMenuItemQuery;
use TheliaCMS\Model\CmsMenuQuery;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContent;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Model\CmsPageSearch;
use TheliaCMS\Model\CmsPageSearchQuery;
use TheliaCMS\Page\CmsUrlService;
use TheliaCMS\Search\SearchTextExtractor;
use TheliaCMS\Settings\CmsSettings;
use TheliaCMS\Settings\SiteMode;
use TheliaCMS\TheliaCMS;
use TheliaLibrary\Model\LibraryImageQuery;

/**
 * Applies an export file to this site.
 *
 * Two rules make the result predictable:
 *
 * 1. **Nothing existing is touched unless asked.** A page already at the
 *    address the file describes, a menu already carrying the code, are left
 *    alone and counted. `$replace` is what overwrites them.
 * 2. **All of it or none of it.** The whole import runs in one transaction, so
 *    a file that turns out to be broken halfway through leaves a site in the
 *    state it was, rather than half a starter kit.
 */
final readonly class SiteImporter
{
    public function __construct(
        private CmsUrlService $urls,
        private MediaReferences $media,
        private SearchTextExtractor $searchText,
        private CmsSettings $settings,
        private MenuCache $menuCache,
    ) {
    }

    public function import(SiteDocument $document, ImportOptions $options): ImportReport
    {
        $report = new ImportReport();

        foreach ($document->reparentedPages() as $uid) {
            $report->warn(\sprintf('Page "%s" refers to a parent that is not in the file: it was imported at the root of the tree.', $uid));
        }

        $connection = Propel::getConnection('TheliaMain');
        $connection->beginTransaction();

        try {
            $mediaMap = $this->mediaMap($document, $report);
            $pageIds = $this->importPages($document, $options, $report, $mediaMap, $connection);

            $this->importBlocks($document, $options, $report, $mediaMap, $connection);
            $this->importMenus($document, $options, $report, $pageIds, $connection);
            $this->importForms($document, $options, $report, $pageIds, $connection);

            if ($options->withSettings) {
                $this->importSettings($document, $report, $pageIds);
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        $this->menuCache->invalidate();

        return $report;
    }

    /**
     * Creates one page from a single-page document — what starting a page from
     * a template does.
     *
     * The page always comes out as a new draft, hidden, whatever the document
     * says: a template is a starting point, and putting a page online is a
     * decision its author takes afterwards, in the editor.
     *
     * @throws \InvalidArgumentException when the document holds no page
     */
    public function importPageFrom(SiteDocument $document, int $parentId, ?string $title, string $locale): CmsPage
    {
        $pages = $document->pages();

        if ([] === $pages) {
            throw new \InvalidArgumentException('This template holds no page.');
        }

        $source = $pages[0];
        $report = new ImportReport();

        $connection = Propel::getConnection('TheliaMain');
        $connection->beginTransaction();

        try {
            $mediaMap = $this->mediaMap($document, $report);

            $page = (new CmsPage())
                ->setParent($parentId)
                ->setPosition((int) ($source['position'] ?? 0))
                ->setVisible(0)
                ->setLayout((string) ($source['layout'] ?? 'default'));

            foreach ($source['translations'] ?? [] as $pageLocale => $translation) {
                $page->setLocale((string) $pageLocale)
                    ->setTitle((string) ($pageLocale === $locale && null !== $title && '' !== $title
                        ? $title
                        : ($translation['title'] ?? '')))
                    ->setMetaTitle($translation['meta_title'] ?? null)
                    ->setMetaDescription($translation['meta_description'] ?? null)
                    ->setOgTitle($translation['og_title'] ?? null)
                    ->setOgDescription($translation['og_description'] ?? null)
                    ->setOgImageId($this->mappedImage($translation['og_image_id'] ?? null, $mediaMap))
                    ->setTwitterCard($translation['twitter_card'] ?? null)
                    ->setNoindex(($translation['noindex'] ?? false) ? 1 : 0)
                    ->setNofollow(($translation['nofollow'] ?? false) ? 1 : 0);
                // `canonical` is left out: it names one exact page.
            }

            // A title only for the locale being edited would leave the page
            // nameless everywhere else, and a page with no title in a locale is
            // a page that never gets an address there.
            if (null !== $title && '' !== $title && !isset($source['translations'][$locale])) {
                $page->setLocale($locale)->setTitle($title);
            }

            $page->save($connection);

            foreach ($source['contents'] ?? [] as $contentLocale => $content) {
                (new CmsPageContent())
                    ->setPageId($page->getId())
                    ->setLocale((string) $contentLocale)
                    ->setDraftProjectData($this->media->remap($content['project_data'] ?? null, $mediaMap))
                    // What the template shows is what was published in it; the
                    // draft columns of the source may hold work in progress.
                    ->setDraftHtml($this->media->remap($content['published_html'] ?? $content['draft_html'] ?? null, $mediaMap))
                    ->setDraftCss($this->media->remap($content['published_css'] ?? $content['draft_css'] ?? null, $mediaMap))
                    ->save($connection);
            }

            foreach ($this->localesOf($page, $source) as $pageLocale) {
                $this->refreshUrl($page, $pageLocale, null, $report);
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        return $page;
    }

    /**
     * @param array<string, mixed> $source
     *
     * @return list<string>
     */
    private function localesOf(CmsPage $page, array $source): array
    {
        $locales = array_keys($source['translations'] ?? []);

        return array_values(array_filter(
            array_map(strval(...), $locales),
            static fn (string $locale): bool => '' !== trim((string) $page->setLocale($locale)->getTitle()),
        ));
    }

    /**
     * Matches the images of the file with the ones already here, by file name.
     *
     * @return array<int, int>
     */
    private function mediaMap(SiteDocument $document, ImportReport $report): array
    {
        $map = [];
        $missing = [];

        foreach ($document->media() as $id => $fileName) {
            $image = LibraryImageQuery::create()->findOneByFileName($fileName);

            if (null === $image) {
                $missing[] = $fileName;

                continue;
            }

            $map[$id] = (int) $image->getId();
        }

        if ([] !== $missing) {
            $report->warn(\sprintf(
                'These images are not in the media library of this site, and the content pointing at them will show a broken image until they are uploaded: %s',
                implode(', ', $missing),
            ));
        }

        return $map;
    }

    /**
     * @param array<int, int> $mediaMap
     *
     * @return array<string, int> page id here, by uid in the file
     */
    private function importPages(
        SiteDocument $document,
        ImportOptions $options,
        ImportReport $report,
        array $mediaMap,
        ConnectionInterface $connection,
    ): array {
        $pageIds = [];

        foreach ($document->pages() as $page) {
            $uid = (string) ($page['uid'] ?? '');
            $translations = $page['translations'] ?? [];

            if ([] === $translations) {
                $report->skipped('pages');

                continue;
            }

            $parentUid = null === ($page['parent'] ?? null) ? null : (string) $page['parent'];
            $parentId = null !== $parentUid ? ($pageIds[$parentUid] ?? 0) : 0;

            $existing = $this->existingPage($translations, $parentId);

            if (null !== $existing && !$options->replace) {
                $pageIds[$uid] = (int) $existing->getId();
                $report->skipped('pages');

                continue;
            }

            $model = $existing ?? new CmsPage();
            $model
                ->setParent($parentId)
                ->setPosition((int) ($page['position'] ?? 0))
                ->setVisible(($page['visible'] ?? true) ? 1 : 0)
                ->setLayout((string) ($page['layout'] ?? 'default'))
                ->setPublishAt($this->date($page['publish_at'] ?? null))
                ->setUnpublishAt($this->date($page['unpublish_at'] ?? null));

            foreach ($translations as $locale => $translation) {
                $model->setLocale((string) $locale)
                    ->setTitle((string) ($translation['title'] ?? ''))
                    ->setMetaTitle($translation['meta_title'] ?? null)
                    ->setMetaDescription($translation['meta_description'] ?? null)
                    ->setOgTitle($translation['og_title'] ?? null)
                    ->setOgDescription($translation['og_description'] ?? null)
                    ->setOgImageId($this->mappedImage($translation['og_image_id'] ?? null, $mediaMap))
                    ->setTwitterCard($translation['twitter_card'] ?? null)
                    ->setCanonical($translation['canonical'] ?? null)
                    ->setNoindex(($translation['noindex'] ?? false) ? 1 : 0)
                    ->setNofollow(($translation['nofollow'] ?? false) ? 1 : 0);
            }

            $model->save($connection);
            $pageIds[$uid] = (int) $model->getId();

            $this->importPageContents($model, $page['contents'] ?? [], $mediaMap, $connection);

            foreach ($translations as $locale => $translation) {
                $this->refreshUrl($model, (string) $locale, $translation['slug'] ?? null, $report);
            }

            null !== $existing ? $report->replaced('pages') : $report->created('pages');
        }

        return $pageIds;
    }

    /**
     * @param array<string, mixed> $contents
     * @param array<int, int>      $mediaMap
     */
    private function importPageContents(CmsPage $page, array $contents, array $mediaMap, ConnectionInterface $connection): void
    {
        foreach ($contents as $locale => $content) {
            $locale = (string) $locale;

            $model = CmsPageContentQuery::create()
                ->filterByPageId($page->getId())
                ->filterByLocale($locale)
                ->findOne()
                ?? (new CmsPageContent())->setPageId($page->getId())->setLocale($locale);

            $publishedHtml = $this->media->remap($content['published_html'] ?? null, $mediaMap);

            $model
                ->setDraftProjectData($this->media->remap($content['project_data'] ?? null, $mediaMap))
                ->setDraftHtml($this->media->remap($content['draft_html'] ?? null, $mediaMap))
                ->setDraftCss($this->media->remap($content['draft_css'] ?? null, $mediaMap))
                ->setPublishedHtml($publishedHtml)
                ->setPublishedCss($this->media->remap($content['published_css'] ?? null, $mediaMap))
                ->setPublishedAt($this->date($content['published_at'] ?? null));

            // The back office reads "edited since it went live" from this
            // column against the publication date. Saving without setting it
            // stamps it with the time of the import, and every page of a
            // restored site then asks to be published again.
            $model->setUpdatedAt(
                $this->date($content['updated_at'] ?? null)
                ?? $model->getPublishedAt()
                ?? new \DateTimeImmutable(),
            );

            $model->save($connection);

            // Without this, an imported site answers nothing at all on its own
            // search page: the index is built at publish time, and importing is
            // not publishing.
            if (null !== $model->getPublishedAt()) {
                $this->index($page, $locale, $publishedHtml, $connection);
            }
        }
    }

    private function index(CmsPage $page, string $locale, ?string $html, ConnectionInterface $connection): void
    {
        $index = CmsPageSearchQuery::create()
            ->filterByPageId($page->getId())
            ->filterByLocale($locale)
            ->findOne()
            ?? (new CmsPageSearch())->setPageId($page->getId())->setLocale($locale);

        $page->setLocale($locale);

        $index->setContent(trim($page->getTitle().' '.$this->searchText->extract($html)))->save($connection);
    }

    /**
     * @param array<int, int> $mediaMap
     */
    private function importBlocks(
        SiteDocument $document,
        ImportOptions $options,
        ImportReport $report,
        array $mediaMap,
        ConnectionInterface $connection,
    ): void {
        foreach ($document->blocks() as $block) {
            $code = trim((string) ($block['code'] ?? ''));

            if ('' === $code) {
                $report->skipped('blocks');

                continue;
            }

            $existing = CmsBlockQuery::create()->findOneByCode($code);

            if (null !== $existing && !$options->replace) {
                $report->skipped('blocks');

                continue;
            }

            $model = $existing ?? (new CmsBlock())->setCode($code);
            $model->setDeletedAt(null);

            foreach ($block['translations'] ?? [] as $locale => $translation) {
                $model->setLocale((string) $locale)->setTitle((string) ($translation['title'] ?? $code));
            }

            $model->save($connection);

            foreach ($block['contents'] ?? [] as $locale => $content) {
                $locale = (string) $locale;

                $contentModel = CmsBlockContentQuery::create()
                    ->filterByBlockId($model->getId())
                    ->filterByLocale($locale)
                    ->findOne()
                    ?? (new CmsBlockContent())->setBlockId($model->getId())->setLocale($locale);

                $contentModel
                    ->setDraftProjectData($this->media->remap($content['project_data'] ?? null, $mediaMap))
                    ->setDraftHtml($this->media->remap($content['draft_html'] ?? null, $mediaMap))
                    ->setDraftCss($this->media->remap($content['draft_css'] ?? null, $mediaMap))
                    ->setPublishedHtml($this->media->remap($content['published_html'] ?? null, $mediaMap))
                    ->setPublishedCss($this->media->remap($content['published_css'] ?? null, $mediaMap))
                    ->setPublishedAt($this->date($content['published_at'] ?? null))
                    ->save($connection);
            }

            null !== $existing ? $report->replaced('blocks') : $report->created('blocks');
        }
    }

    /**
     * @param array<string, int> $pageIds
     */
    private function importMenus(
        SiteDocument $document,
        ImportOptions $options,
        ImportReport $report,
        array $pageIds,
        ConnectionInterface $connection,
    ): void {
        foreach ($document->menus() as $menu) {
            $code = trim((string) ($menu['code'] ?? ''));

            if ('' === $code) {
                $report->skipped('menus');

                continue;
            }

            $existing = CmsMenuQuery::create()->findOneByCode($code);

            if (null !== $existing && !$options->replace) {
                $report->skipped('menus');

                continue;
            }

            $model = $existing ?? (new CmsMenu())->setCode($code);

            foreach ($menu['translations'] ?? [] as $locale => $translation) {
                $model->setLocale((string) $locale)->setTitle((string) ($translation['title'] ?? $code));
            }

            $model->save($connection);

            // The entries of a replaced menu are rebuilt rather than merged:
            // merging two trees by position produces an order nobody asked for.
            CmsMenuItemQuery::create()->filterByMenuId($model->getId())->delete($connection);

            $itemIds = [];

            foreach ($menu['items'] ?? [] as $item) {
                $targetType = (string) ($item['target_type'] ?? 'page');
                $targetId = $this->menuTarget($item, $targetType, $pageIds, $report);

                if ('page' === $targetType && null === $targetId) {
                    $report->skipped('menu entries');

                    continue;
                }

                $parentUid = null === ($item['parent'] ?? null) ? null : (string) $item['parent'];

                $entry = (new CmsMenuItem())
                    ->setMenuId($model->getId())
                    ->setParent(null !== $parentUid ? ($itemIds[$parentUid] ?? 0) : 0)
                    ->setPosition((int) ($item['position'] ?? 0))
                    ->setTargetType($targetType)
                    ->setTargetId($targetId)
                    ->setUrl($item['url'] ?? null)
                    ->setOpenNewTab(($item['open_new_tab'] ?? false) ? 1 : 0);

                foreach ($item['translations'] ?? [] as $locale => $translation) {
                    $entry->setLocale((string) $locale)->setLabel($translation['label'] ?? null);
                }

                $entry->save($connection);
                $itemIds[(string) ($item['uid'] ?? '')] = (int) $entry->getId();
            }

            null !== $existing ? $report->replaced('menus') : $report->created('menus');
        }
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, int>   $pageIds
     */
    private function menuTarget(array $item, string $targetType, array $pageIds, ImportReport $report): ?int
    {
        if ('page' !== $targetType) {
            $rawId = $item['target_id'] ?? null;

            if (null !== $rawId) {
                $report->warn(\sprintf(
                    'A menu entry points at a %s of the catalogue by its number (%d): check that it is the right one on this site.',
                    $targetType,
                    (int) $rawId,
                ));
            }

            return null !== $rawId ? (int) $rawId : null;
        }

        $uid = $item['target'] ?? null;

        if (null === $uid) {
            return null;
        }

        $pageId = $pageIds[(string) $uid] ?? null;

        if (null === $pageId) {
            $report->warn(\sprintf('A menu entry points at page "%s", which is not in the file: the entry was left out.', (string) $uid));
        }

        return $pageId;
    }

    /**
     * @param array<string, int> $pageIds
     */
    private function importForms(
        SiteDocument $document,
        ImportOptions $options,
        ImportReport $report,
        array $pageIds,
        ConnectionInterface $connection,
    ): void {
        foreach ($document->forms() as $form) {
            $code = trim((string) ($form['code'] ?? ''));

            if ('' === $code) {
                $report->skipped('forms');

                continue;
            }

            $existing = CmsFormQuery::create()->findOneByCode($code);

            if (null !== $existing && !$options->replace) {
                $report->skipped('forms');

                continue;
            }

            $model = $existing ?? (new CmsForm())->setCode($code);
            $privacyUid = $form['privacy_policy_page'] ?? null;

            $model
                ->setActive(($form['active'] ?? true) ? 1 : 0)
                ->setRecipients($form['recipients'] ?? null)
                ->setStoreSubmissions(($form['store_submissions'] ?? true) ? 1 : 0)
                ->setRetentionDays((int) ($form['retention_days'] ?? 365))
                ->setSendReceipt(($form['send_receipt'] ?? false) ? 1 : 0)
                ->setLeadEvent(($form['lead_event'] ?? true) ? 1 : 0)
                ->setPrivacyPolicyPageId(null !== $privacyUid ? ($pageIds[(string) $privacyUid] ?? null) : null)
                ->setDeletedAt(null);

            foreach ($form['translations'] ?? [] as $locale => $translation) {
                $model->setLocale((string) $locale)
                    ->setTitle((string) ($translation['title'] ?? $code))
                    ->setDescription($translation['description'] ?? null)
                    ->setSubmitLabel($translation['submit_label'] ?? null)
                    ->setSuccessMessage($translation['success_message'] ?? null)
                    ->setLegalNotice($translation['legal_notice'] ?? null)
                    ->setReceiptSubject($translation['receipt_subject'] ?? null)
                    ->setReceiptBody($translation['receipt_body'] ?? null);
            }

            $model->save($connection);

            // Same reasoning as the menu entries: the fields of a form are its
            // shape, and half of an old shape mixed with half of a new one is
            // not a form anyone designed.
            CmsFormFieldQuery::create()->filterByFormId($model->getId())->delete($connection);

            foreach ($form['fields'] ?? [] as $field) {
                $fieldModel = (new CmsFormField())
                    ->setFormId($model->getId())
                    ->setName((string) ($field['name'] ?? ''))
                    ->setType((string) ($field['type'] ?? 'text'))
                    ->setPosition((int) ($field['position'] ?? 0))
                    ->setRequired(($field['required'] ?? false) ? 1 : 0)
                    ->setOptions($field['options'] ?? null);

                foreach ($field['translations'] ?? [] as $locale => $translation) {
                    $fieldModel->setLocale((string) $locale)
                        ->setLabel($translation['label'] ?? null)
                        ->setPlaceholder($translation['placeholder'] ?? null)
                        ->setHelp($translation['help'] ?? null)
                        ->setChoices($translation['choices'] ?? null);
                }

                $fieldModel->save($connection);
            }

            null !== $existing ? $report->replaced('forms') : $report->created('forms');
        }
    }

    /**
     * @param array<string, int> $pageIds
     */
    private function importSettings(SiteDocument $document, ImportReport $report, array $pageIds): void
    {
        $settings = $document->settings();

        if ([] === $settings) {
            return;
        }

        $page = static fn (?string $uid): ?int => null !== $uid ? ($pageIds[$uid] ?? null) : null;

        $homePageId = $page($settings['home_page'] ?? null);

        $this->settings->save(
            SiteMode::fromStorage($settings['site_mode'] ?? null),
            $page($settings['404_page'] ?? null),
            $this->settings->isMaintenanceActive(),
            implode("\n", $this->settings->maintenanceAllowlist()),
            $page($settings['maintenance_page'] ?? null),
            (int) ($settings['trash_retention_days'] ?? 30),
            (int) ($settings['http_cache_ttl'] ?? 0),
        );

        TheliaCMS::setConfigValue('home_page_id', (string) ($homePageId ?? ''));

        $report->created('settings');
    }

    /**
     * The page already published at the address this document describes, if any.
     *
     * Identity is the address rather than the title: two pages of the same site
     * may well be called "Contact", and none of them may be at the same place
     * in the tree.
     *
     * @param array<string, mixed> $translations
     */
    private function existingPage(array $translations, int $parentId): ?CmsPage
    {
        foreach ($translations as $locale => $translation) {
            $slug = trim((string) ($translation['slug'] ?? ''));

            if ('' === $slug) {
                continue;
            }

            $url = $this->addressUnder($parentId, (string) $locale, $slug);

            $existing = RewritingUrlQuery::create()
                ->filterByUrl($url)
                ->filterByView(TheliaCMS::PAGE_VIEW)
                ->filterByViewLocale((string) $locale)
                ->filterByRedirected(null, Criteria::ISNULL)
                ->findOne();

            if (null === $existing) {
                continue;
            }

            $page = CmsPageQuery::create()
                ->filterByDeletedAt(null, Criteria::ISNULL)
                ->findPk((int) $existing->getViewId());

            if ($page instanceof CmsPage) {
                return $page;
            }
        }

        return null;
    }

    private function addressUnder(int $parentId, string $locale, string $slug): string
    {
        if (0 === $parentId) {
            return $slug;
        }

        $parent = CmsPageQuery::create()->findPk($parentId);
        $parentUrl = $parent?->getRewrittenUrl($locale);

        return null !== $parentUrl && '' !== $parentUrl ? $parentUrl.'/'.$slug : $slug;
    }

    private function refreshUrl(CmsPage $page, string $locale, ?string $slug, ImportReport $report): void
    {
        try {
            $this->urls->refresh($page, $locale, null !== $slug && '' !== $slug ? $slug : null);
        } catch (\InvalidArgumentException $exception) {
            // A slug this site reserves — the address of its own back office,
            // for one. The page is kept, under an address derived from its
            // title, rather than the whole import being refused.
            $this->urls->refresh($page, $locale);
            $report->warn(\sprintf('Page "%s" could not keep its address in %s: %s', $page->getTitle(), $locale, $exception->getMessage()));
        }
    }

    /**
     * @param array<int, int> $mediaMap
     */
    private function mappedImage(mixed $imageId, array $mediaMap): ?int
    {
        if (null === $imageId) {
            return null;
        }

        return $mediaMap[(int) $imageId] ?? null;
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
