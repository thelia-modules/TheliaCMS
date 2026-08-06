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

use Propel\Runtime\Propel;
use Thelia\Core\Security\SecurityContext;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContent;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Model\CmsPageRevision;
use TheliaCMS\Model\CmsPageRevisionQuery;
use TheliaCMS\Page\CmsUrlService;

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
    ) {
    }

    public function saveDraft(CmsPage $page, string $locale, PageDraft $draft): void
    {
        $connection = Propel::getConnection('TheliaMain');
        $connection->beginTransaction();

        try {
            $adminId = $this->securityContext->getAdminUser()?->getId();

            if ($page->isNew()) {
                $page->setCreatedBy($adminId);
            }

            $page->setUpdatedBy($adminId);
            $page->setLocale($locale)->setTitle($draft->title);
            $page->save($connection);

            $content = $this->contentFor($page, $locale);
            $content->setDraftHtml($draft->html)
                ->setDraftUpdatedBy($adminId)
                ->save($connection);

            $this->urls->refresh($page, $locale, $draft->slug);

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }
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
            $content = $this->contentFor($page, $locale);
            $content->setPublishedHtml($content->getDraftHtml())
                ->setPublishedCss($content->getDraftCss())
                ->setPublishedAt(new \DateTimeImmutable())
                ->save($connection);

            $this->snapshot($page, $locale, $content, $connection);

            $connection->commit();
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
    }

    /**
     * Soft delete: the row stays so the bin can restore it, but the rewritten
     * URLs go immediately — leaving them would keep routing visitors to a page
     * that no longer resolves, which is a 500 and not a 404.
     */
    public function moveToTrash(CmsPage $page): void
    {
        $page->setDeletedAt(new \DateTimeImmutable())->save();

        \Thelia\Model\RewritingUrlQuery::create()
            ->filterByView($page->getRewrittenUrlViewName())
            ->filterByViewId((string) $page->getId())
            ->delete();
    }

    public function restore(CmsPage $page, string $locale): void
    {
        $page->setDeletedAt(null)->save();
        $this->urls->refresh($page, $locale);
    }

    public function toggleVisibility(CmsPage $page): void
    {
        $page->setVisible(1 === $page->getVisible() ? 0 : 1)->save();
    }

    /**
     * Moves a page one slot up or down among its siblings.
     */
    public function move(CmsPage $page, int $direction): void
    {
        $siblings = CmsPageQuery::create()
            ->filterByParent($page->getParent())
            ->filterByDeletedAt(null, \Propel\Runtime\ActiveQuery\Criteria::ISNULL)
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
            ->orderByCreatedAt(\Propel\Runtime\ActiveQuery\Criteria::DESC)
            ->offset(self::REVISION_RETENTION)
            ->limit(1000)
            ->find();

        foreach ($obsolete as $revision) {
            $revision->delete($connection);
        }
    }
}
