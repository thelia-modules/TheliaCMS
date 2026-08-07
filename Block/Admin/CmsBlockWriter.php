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

namespace TheliaCMS\Block\Admin;

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use TheliaCMS\Block\BlockUsageFinder;
use TheliaCMS\Builder\ImageRewriter;
use TheliaCMS\Builder\PageContentNormalizer;
use TheliaCMS\Builder\PublishedContentSanitizer;
use TheliaCMS\Http\CachePurger;
use TheliaCMS\Http\CacheTags;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Model\CmsBlockContent;
use TheliaCMS\Model\CmsBlockContentQuery;
use TheliaCMS\Page\Admin\BuilderContent;
use TheliaCMS\Page\Admin\CmsActivityLog;
use TheliaCMS\Partial\PartialCache;
use TheliaCMS\Security\CmsResources;

/**
 * Every write to a reusable block goes through here.
 *
 * A block is shown on pages that were published long ago, so publishing one
 * changes what visitors see without any page being touched: dropping the
 * cached fragments is part of the write, not an afterthought left to callers.
 */
final readonly class CmsBlockWriter
{
    public function __construct(
        private SecurityContext $securityContext,
        private CmsActivityLog $activityLog,
        private PageContentNormalizer $normalizer,
        private PublishedContentSanitizer $sanitizer,
        private ImageRewriter $images,
        private PartialCache $partialCache,
        private CachePurger $httpCache,
        private BlockUsageFinder $usages,
    ) {
    }

    public function save(CmsBlock $block, string $locale, string $code, string $title): void
    {
        $wasNew = $block->isNew();
        $adminId = $this->securityContext->getAdminUser()?->getId();

        if ($wasNew) {
            $block->setCreatedBy($adminId);
        }

        $block->setCode($code)
            ->setUpdatedBy($adminId)
            ->setLocale($locale)
            ->setTitle($title)
            ->save();

        // The row is created here so the block shows up in the language being
        // edited even before anything has been laid out in it.
        $content = $this->contentFor($block, $locale);

        if ($content->isNew()) {
            $content->save();
        }

        $this->partialCache->invalidateBlocks();
        $this->purgePagesUsing($block);
        $this->activityLog->record($wasNew ? 'CREATE' : 'UPDATE', (int) $block->getId(), \sprintf('CMS block "%s" saved in %s', $code, $locale));
    }

    /**
     * Stores what the builder produced. Pages keep showing the published
     * version until the block is published in its turn.
     */
    public function saveContent(CmsBlock $block, string $locale, BuilderContent $content): void
    {
        $html = $this->sanitizer->html($this->normalizer->html($content->html), $this->mayPublishCustomCode());

        $this->contentFor($block, $locale)
            ->setDraftProjectData($content->projectData)
            ->setDraftHtml($html)
            ->setDraftCss($this->sanitizer->css($this->normalizer->css($content->css)))
            ->setDraftUpdatedBy($this->securityContext->getAdminUser()?->getId())
            ->save();

        $this->activityLog->record('UPDATE', (int) $block->getId(), \sprintf('CMS block #%d content edited in %s', $block->getId(), $locale));
    }

    /**
     * Puts the draft online — on every page using the block at once.
     */
    public function publish(CmsBlock $block, string $locale): void
    {
        $content = $this->contentFor($block, $locale);

        $html = $this->images->rewrite(
            $this->sanitizer->html($this->normalizer->html($content->getDraftHtml()), $this->mayPublishCustomCode()),
        );

        $content->setPublishedHtml($html)
            ->setPublishedCss($this->sanitizer->css($this->normalizer->css($content->getDraftCss())))
            ->setPublishedAt(new \DateTimeImmutable())
            ->save();

        $this->partialCache->invalidateBlocks();
        $this->purgePagesUsing($block);
        $this->activityLog->record('PUBLISH', (int) $block->getId(), \sprintf(
            'CMS block #%d published in %s, used by %d page(s)',
            $block->getId(),
            $locale,
            \count($this->usages->pagesUsing((int) $block->getId())),
        ));
    }

    /**
     * Soft delete. A block still used by a page is refused: it would leave a
     * hole in pages nobody is looking at.
     *
     * @throws \DomainException when the block is still in use
     */
    public function delete(CmsBlock $block): void
    {
        $usages = $this->usages->pagesUsing((int) $block->getId());

        if ([] !== $usages) {
            throw new \DomainException('This block is still used by a page. Remove it from those pages first.');
        }

        $block->setDeletedAt(new \DateTimeImmutable())->save();

        $this->partialCache->invalidateBlocks();
        $this->purgePagesUsing($block);
        $this->activityLog->record('DELETE', (int) $block->getId(), \sprintf('CMS block #%d deleted', $block->getId()));
    }

    private function contentFor(CmsBlock $block, string $locale): CmsBlockContent
    {
        $content = CmsBlockContentQuery::create()
            ->filterByBlockId($block->getId())
            ->filterByLocale($locale)
            ->findOne();

        return $content ?? (new CmsBlockContent())->setBlockId($block->getId())->setLocale($locale);
    }

    private function mayPublishCustomCode(): bool
    {
        return $this->securityContext->isGranted(['ADMIN'], [CmsResources::CUSTOM_CODE], [], [AccessManager::UPDATE]);
    }

    /**
     * Drops the cached pages a block appears on.
     *
     * Found by looking through the published HTML rather than from a table of
     * usages: the block is placed in the editor, and a usage table would only
     * be as right as the last time somebody remembered to write to it.
     */
    private function purgePagesUsing(CmsBlock $block): void
    {
        if ($block->isNew()) {
            return;
        }

        $tags = [];

        foreach ($this->usages->pagesUsing((int) $block->getId()) as $usage) {
            $tags[] = CacheTags::page((int) $usage['page']->getId());
        }

        $this->httpCache->purge($tags);
    }
}
