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

namespace TheliaCMS\Page\Admin;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\LangQuery;
use Thelia\Model\RewritingUrlQuery;
use TheliaCMS\Builder\ImageRewriter;
use TheliaCMS\Builder\PageContentNormalizer;
use TheliaCMS\Builder\PublishedContentSanitizer;
use TheliaCMS\Menu\MenuCache;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContent;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Model\CmsPageRevision;
use TheliaCMS\Model\CmsPageRevisionQuery;
use TheliaCMS\Model\CmsPageSearch;
use TheliaCMS\Model\CmsPageSearchQuery;
use TheliaCMS\Page\CmsUrlService;
use TheliaCMS\Search\SearchTextExtractor;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\TheliaCMS;

/**
 * Every write to a page goes through here, so the side effects that must travel
 * with a change — rewritten URLs, revision snapshots, search payload — cannot
 * be forgotten by a caller.
 */
final readonly class CmsPageWriter
{
    /** Revisions kept per (page, locale) before the oldest ones are dropped. */
    private const int REVISION_RETENTION = 20;

    public function __construct(
        private CmsUrlService $urls,
        private SecurityContext $securityContext,
        private CmsActivityLog $activityLog,
        private PageContentNormalizer $normalizer,
        private PublishedContentSanitizer $sanitizer,
        private ImageRewriter $images,
        private SearchTextExtractor $searchText,
        // Menus hold the address and the title of the pages they point at, and
        // they are cached: a page that is renamed, published, unpublished or
        // binned has to drop them, or the navigation of the site keeps pointing
        // at what the page used to be.
        private MenuCache $menuCache,
    ) {
    }

    public function saveDraft(CmsPage $page, string $locale, PageDraft $draft): void
    {
        $connection = Propel::getConnection('TheliaMain');
        $connection->beginTransaction();

        try {
            $adminId = $this->securityContext->getAdminUser()?->getId();
            $wasNew = $page->isNew();

            if ($wasNew) {
                $page->setCreatedBy($adminId);
            }

            $page->setUpdatedBy($adminId);
            $page->setLocale($locale)->setTitle($draft->title);
            $page->save($connection);

            // The content itself belongs to the builder screen. The row is
            // still created here: it is what tells the module which locales a
            // page exists in, when URLs are regenerated.
            $content = $this->contentFor($page, $locale);

            if ($content->isNew()) {
                $content->save($connection);
            }

            $this->urls->refresh($page, $locale, $draft->slug);

            $connection->commit();

            $this->menuCache->invalidate();
            $this->activityLog->record($wasNew ? 'CREATE' : 'UPDATE', (int) $page->getId(), \sprintf('CMS page "%s" saved in %s', $draft->title, $locale));
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }
    }

    /**
     * Stores what the builder produced, without touching the published
     * snapshot: saving in the editor never changes what visitors see.
     */
    public function saveContent(CmsPage $page, string $locale, BuilderContent $content): void
    {
        $adminId = $this->securityContext->getAdminUser()?->getId();

        // Drafts are filtered too, not only what goes online: the draft is
        // loaded back into the editor of whoever opens the page next, so a
        // script left in it would run in their back office.
        $html = $this->sanitizer->html($this->normalizer->html($content->html), $this->mayPublishCustomCode());

        $this->contentFor($page, $locale)
            ->setDraftProjectData($content->projectData)
            ->setDraftHtml($html)
            ->setDraftCss($this->sanitizer->css($this->normalizer->css($content->css)))
            ->setDraftUpdatedBy($adminId)
            ->save();

        $this->activityLog->record('UPDATE', (int) $page->getId(), \sprintf('CMS page #%d content edited in %s', $page->getId(), $locale));
    }

    /**
     * Promotes the draft to the published snapshot and takes a revision, in one
     * transaction: a half-published page would be served to visitors.
     */
    public function publish(CmsPage $page, string $locale): void
    {
        $connection = Propel::getConnection('TheliaMain');
        $connection->beginTransaction();

        try {
            $withCustomCode = $this->mayPublishCustomCode();

            $content = $this->contentFor($page, $locale);
            $html = $this->images->rewrite(
                $this->sanitizer->html($this->normalizer->html($content->getDraftHtml()), $withCustomCode),
            );
            $css = $this->sanitizer->css($this->normalizer->css($content->getDraftCss()));

            $content->setPublishedHtml($html)
                ->setPublishedCss($css)
                ->setPublishedAt(new \DateTimeImmutable())
                ->save($connection);

            $this->indexForSearch($page, $locale, $html, $connection);
            $this->snapshot($page, $locale, $content, $connection);

            $connection->commit();

            $this->menuCache->invalidate();
            $this->activityLog->record('PUBLISH', (int) $page->getId(), \sprintf(
                'CMS page #%d published in %s%s',
                $page->getId(),
                $locale,
                // Traced: this page went online through the wider allow list.
                $withCustomCode ? ' (custom code allowed)' : '',
            ));
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }
    }

    public function unpublish(CmsPage $page, string $locale): void
    {
        $content = CmsPageContentQuery::create()
            ->filterByPageId($page->getId())
            ->filterByLocale($locale)
            ->findOne();

        $content?->setPublishedAt(null)->save();

        $this->menuCache->invalidate();
        $this->activityLog->record('UNPUBLISH', (int) $page->getId(), \sprintf('CMS page #%d unpublished in %s', $page->getId(), $locale));
    }

    /**
     * Soft delete, cascading down the subtree.
     *
     * The cascade is done here rather than by a database constraint: a real
     * `ON DELETE CASCADE` on the parent would hard-delete the children behind
     * the bin. Leaving descendants alone is not an option either — they would
     * vanish from the tree (their parent is gone from it) while still being
     * served on the front.
     *
     * Rewritten URLs go immediately: kept, they would route visitors to a page
     * that no longer resolves, which is a 500 and not a 404.
     *
     * @return list<int> the pages actually binned, deepest first
     */
    public function moveToTrash(CmsPage $page): array
    {
        $connection = Propel::getConnection('TheliaMain');
        $connection->beginTransaction();

        try {
            $deletedAt = new \DateTimeImmutable();
            $branch = $this->branchOf($page);

            foreach ($branch as $node) {
                $node->setDeletedAt($deletedAt)->save($connection);

                RewritingUrlQuery::create()
                    ->filterByView($node->getRewrittenUrlViewName())
                    ->filterByViewId((string) $node->getId())
                    ->delete($connection);
            }

            $connection->commit();

            $this->menuCache->invalidate();

            $ids = array_map(static fn (CmsPage $node): int => (int) $node->getId(), $branch);
            $this->activityLog->record('DELETE', (int) $page->getId(), \sprintf('CMS pages moved to the bin: %s', implode(', ', $ids)));

            return $ids;
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }
    }

    /**
     * Restores a page and everything binned underneath it. A descendant whose
     * own parent is still in the bin stays there: restoring it would put it
     * back in the tree under a page nobody can see.
     */
    public function restore(CmsPage $page, string $locale): void
    {
        if (!$this->isRestorable($page)) {
            throw new \DomainException('Restore the parent page first: this page would come back under a page that is still in the bin.');
        }

        $connection = Propel::getConnection('TheliaMain');
        $connection->beginTransaction();

        try {
            foreach ($this->branchOf($page, includeDeleted: true) as $node) {
                $node->setDeletedAt(null)->save($connection);

                foreach ($this->publishedLocalesOf($node) as $contentLocale) {
                    $this->urls->refresh($node, $contentLocale);
                }

                $this->urls->refresh($node, $locale);
            }

            $connection->commit();

            $this->menuCache->invalidate();
            $this->activityLog->record('RESTORE', (int) $page->getId(), \sprintf('CMS page #%d restored from the bin', $page->getId()));
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }
    }

    /**
     * Copies a page and its content in every locale, as a draft.
     *
     * The copy is deliberately never published and never inherits the
     * publication window: duplicating a live page must not put a second live
     * page online behind the editor's back.
     */
    public function duplicate(CmsPage $page, string $locale, string $titleSuffix): CmsPage
    {
        $connection = Propel::getConnection('TheliaMain');
        $connection->beginTransaction();

        try {
            $copy = new CmsPage();
            $copy->setParent($page->getParent())
                ->setPosition($page->getPosition() + 1)
                ->setVisible(0)
                ->setLayout($page->getLayout())
                ->setCreatedBy($this->securityContext->getAdminUser()?->getId());

            foreach (LangQuery::create()->filterByActive(1)->find() as $lang) {
                $source = $page->setLocale($lang->getLocale());
                $title = trim((string) $source->getTitle());

                if ('' === $title) {
                    continue;
                }

                $copy->setLocale($lang->getLocale())
                    ->setTitle($title.' '.$titleSuffix)
                    ->setMetaTitle($source->getMetaTitle())
                    ->setMetaDescription($source->getMetaDescription())
                    ->setOgTitle($source->getOgTitle())
                    ->setOgDescription($source->getOgDescription())
                    ->setOgImageId($source->getOgImageId())
                    ->setTwitterCard($source->getTwitterCard())
                    ->setNoindex($source->getNoindex())
                    ->setNofollow($source->getNofollow());
                // `canonical` is not copied: it points at this exact page.
            }

            $copy->save($connection);

            $contents = CmsPageContentQuery::create()->filterByPageId($page->getId())->find();

            foreach ($contents as $content) {
                (new CmsPageContent())
                    ->setPageId($copy->getId())
                    ->setLocale($content->getLocale())
                    ->setDraftProjectData($content->getDraftProjectData())
                    ->setDraftHtml($content->getDraftHtml() ?? $content->getPublishedHtml())
                    ->setDraftCss($content->getDraftCss() ?? $content->getPublishedCss())
                    ->setDraftUpdatedBy($this->securityContext->getAdminUser()?->getId())
                    ->save($connection);

                $this->urls->refresh($copy, (string) $content->getLocale());
            }

            $connection->commit();

            $this->activityLog->record('CREATE', (int) $copy->getId(), \sprintf('CMS page #%d duplicated from #%d', $copy->getId(), $page->getId()));

            return $copy;
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }
    }

    /**
     * Makes this page the site home page.
     */
    public function setAsHome(CmsPage $page): void
    {
        TheliaCMS::setConfigValue('home_page_id', (string) $page->getId());

        $this->activityLog->record('UPDATE', (int) $page->getId(), \sprintf('CMS page #%d set as the home page', $page->getId()));
    }

    /**
     * A binned page can come back only if its parent is still in the tree —
     * otherwise it would reappear nowhere the editor can reach it.
     */
    public function isRestorable(CmsPage $page): bool
    {
        $parentId = (int) $page->getParent();

        if (0 === $parentId) {
            return true;
        }

        $parent = CmsPageQuery::create()->findPk($parentId);

        return null === $parent || null === $parent->getDeletedAt();
    }

    /**
     * The page and all of its descendants, parents before children.
     *
     * @return list<CmsPage>
     */
    private function branchOf(CmsPage $page, bool $includeDeleted = false): array
    {
        $branch = [$page];
        $frontier = [(int) $page->getId()];
        $guard = 0;

        while ([] !== $frontier && ++$guard < 20) {
            $query = CmsPageQuery::create()->filterByParent($frontier);

            if (!$includeDeleted) {
                $query->filterByDeletedAt(null, Criteria::ISNULL);
            }

            $children = iterator_to_array($query->find(), false);
            $frontier = [];

            foreach ($children as $child) {
                $branch[] = $child;
                $frontier[] = (int) $child->getId();
            }
        }

        return $branch;
    }

    /**
     * @return list<string>
     */
    private function publishedLocalesOf(CmsPage $page): array
    {
        $locales = CmsPageContentQuery::create()
            ->filterByPageId($page->getId())
            ->select(['Locale'])
            ->find()
            ->toArray();

        return array_values(array_unique(array_map(strval(...), $locales)));
    }

    public function toggleVisibility(CmsPage $page): void
    {
        $page->setVisible(1 === $page->getVisible() ? 0 : 1)->save();

        $this->activityLog->record(
            'UPDATE',
            (int) $page->getId(),
            \sprintf('CMS page #%d is now %s', $page->getId(), 1 === $page->getVisible() ? 'online' : 'offline')
        );
    }

    /**
     * Moves a page one slot up or down among its siblings.
     */
    public function move(CmsPage $page, int $direction): void
    {
        $siblings = CmsPageQuery::create()
            ->filterByParent($page->getParent())
            ->filterByDeletedAt(null, Criteria::ISNULL)
            ->orderByPosition()
            ->find();

        $ordered = iterator_to_array($siblings, false);
        $index = null;

        foreach ($ordered as $offset => $sibling) {
            if ($sibling->getId() === $page->getId()) {
                $index = $offset;
                break;
            }
        }

        $target = $index + ($direction < 0 ? -1 : 1);

        if (null === $index || $target < 0 || $target >= \count($ordered)) {
            return;
        }

        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        foreach ($ordered as $offset => $sibling) {
            $sibling->setPosition($offset + 1)->save();
        }
    }

    private function contentFor(CmsPage $page, string $locale): CmsPageContent
    {
        $content = CmsPageContentQuery::create()
            ->filterByPageId($page->getId())
            ->filterByLocale($locale)
            ->findOne();

        return $content ?? (new CmsPageContent())->setPageId($page->getId())->setLocale($locale);
    }

    /**
     * Whether the author may go beyond the standard allow list — embedding an
     * iframe, essentially. Anything else the editor produces is filtered the
     * same way for everyone.
     */
    private function mayPublishCustomCode(): bool
    {
        return $this->securityContext->isGranted(['ADMIN'], [CmsResources::CUSTOM_CODE], [], [AccessManager::UPDATE]);
    }

    /**
     * Refreshes the full-text payload of the page, so the front-office search
     * queries plain text and never the HTML columns.
     */
    private function indexForSearch(CmsPage $page, string $locale, ?string $html, ConnectionInterface $connection): void
    {
        $index = CmsPageSearchQuery::create()
            ->filterByPageId($page->getId())
            ->filterByLocale($locale)
            ->findOne()
            ?? (new CmsPageSearch())->setPageId($page->getId())->setLocale($locale);

        $page->setLocale($locale);

        $index->setContent(trim($page->getTitle().' '.$this->searchText->extract($html)))
            ->save($connection);
    }

    private function snapshot(CmsPage $page, string $locale, CmsPageContent $content, $connection): void
    {
        (new CmsPageRevision())
            ->setPageId($page->getId())
            ->setLocale($locale)
            ->setProjectData($content->getDraftProjectData())
            ->setHtml($content->getPublishedHtml())
            ->setCss($content->getPublishedCss())
            ->setCreatedBy($this->securityContext->getAdminUser()?->getId())
            ->save($connection);

        $obsolete = CmsPageRevisionQuery::create()
            ->filterByPageId($page->getId())
            ->filterByLocale($locale)
            ->orderByCreatedAt(Criteria::DESC)
            ->offset(self::REVISION_RETENTION)
            ->limit(1000)
            ->find();

        foreach ($obsolete as $revision) {
            $revision->delete($connection);
        }
    }
}
