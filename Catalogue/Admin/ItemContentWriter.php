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

namespace TheliaCMS\Catalogue\Admin;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use TheliaCMS\Catalogue\CatalogueItemType;
use TheliaCMS\Catalogue\ItemContentRepository;
use TheliaCMS\Content\Document;
use TheliaCMS\Content\DocumentRenderer;
use TheliaCMS\Content\DocumentValidator;
use TheliaCMS\Content\InvalidDocumentException;
use TheliaCMS\Content\Pipeline\EmptyContentChecker;
use TheliaCMS\Content\Pipeline\HeadingChecker;
use TheliaCMS\Content\Pipeline\ImageRewriter;
use TheliaCMS\Content\Pipeline\PageContentNormalizer;
use TheliaCMS\Content\Pipeline\PublishedContentSanitizer;
use TheliaCMS\Http\CachePurger;
use TheliaCMS\Model\CmsItemContent;
use TheliaCMS\Model\CmsItemContentQuery;
use TheliaCMS\Model\CmsItemRevision;
use TheliaCMS\Model\CmsItemRevisionQuery;
use TheliaCMS\Page\Admin\CmsActivityLog;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\Settings\CmsSettings;

/**
 * Every write to the document of a catalogue item goes through here. The
 * document is validated, rendered and filtered the way the content of a page
 * is (CmsPageWriter), and published the same way: a draft that visitors never
 * see, then a snapshot taken in one transaction with its revision.
 */
final readonly class ItemContentWriter
{
    /** Revisions kept per item and locale before the oldest ones are dropped. */
    private const int REVISION_RETENTION = 20;

    public function __construct(
        private SecurityContext $securityContext,
        private CmsActivityLog $activityLog,
        private PageContentNormalizer $normalizer,
        private PublishedContentSanitizer $sanitizer,
        private EmptyContentChecker $emptiness,
        private ImageRewriter $images,
        private DocumentValidator $validator,
        private DocumentRenderer $renderer,
        private HeadingChecker $headings,
        private CmsSettings $settings,
        private CachePurger $httpCache,
        private ItemContentRepository $contents,
    ) {
    }

    /**
     * Stores the document and its rendering, without touching the published
     * snapshot: saving never changes what visitors see.
     *
     * @throws InvalidDocumentException when the document cannot be stored
     */
    public function saveContent(CatalogueItemType $type, int $id, string $locale, Document $document): void
    {
        $document = $this->validator->validate($document);

        // Filtered like the draft of a page: the preview shows it and the
        // back office loads it back.
        $html = $this->sanitizer->html($this->normalizer->html($this->renderer->render($document, $locale)), $this->mayPublishCustomCode());

        $this->contentFor($type, $id, $locale)
            ->setDraftDocument($document->toJson())
            ->setDraftHtml($html)
            ->setDraftUpdatedBy($this->securityContext->getAdminUser()?->getId())
            ->save();

        $this->activityLog->record('UPDATE', $id, \sprintf('CMS content of the %s #%d edited in %s', $type->value, $id, $locale));
    }

    /**
     * Promotes the draft to the published snapshot and takes a revision, in
     * one transaction.
     *
     * @throws EmptyItemContentException     when the draft would put nothing online
     * @throws ItemHeadingStructureException when the headings are broken and the site keeps such a document offline
     */
    public function publish(CatalogueItemType $type, int $id, string $locale): void
    {
        $connection = Propel::getConnection('TheliaMain');
        $connection->beginTransaction();

        try {
            $withCustomCode = $this->mayPublishCustomCode();
            $content = $this->contentFor($type, $id, $locale);
            $html = $this->publishableHtml($type, $id, $locale, $content, $withCustomCode);

            $content->setPublishedDocument($content->getDraftDocument())
                ->setPublishedHtml($html)
                ->setPublishedAt(new \DateTimeImmutable())
                ->save($connection);

            $this->snapshot($content, $connection);

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        $this->httpCache->purge([$type->cacheKey($id)]);
        $this->activityLog->record('PUBLISH', $id, \sprintf(
            'CMS content of the %s #%d published in %s%s',
            $type->value,
            $id,
            $locale,
            $withCustomCode ? ' (custom code allowed)' : '',
        ));
    }

    /**
     * Takes the document of the item offline in this language: the page of
     * the item shows nothing of it any more, the draft stays.
     */
    public function unpublish(CatalogueItemType $type, int $id, string $locale): void
    {
        $this->contents->find($type, $id, $locale)?->setPublishedAt(null)
            ->setPublishedDocument(null)
            ->setPublishedHtml(null)
            ->save();

        $this->httpCache->purge([$type->cacheKey($id)]);
        $this->activityLog->record('UNPUBLISH', $id, \sprintf('CMS content of the %s #%d unpublished in %s', $type->value, $id, $locale));
    }

    /**
     * Forgets everything the module holds of an item the shop deleted: no
     * foreign key ties the two (docs/adr/0011), so nothing else would.
     */
    public function forget(CatalogueItemType $type, int $id): void
    {
        CmsItemContentQuery::create()->filterByItemType($type->value)->filterByItemId($id)->delete();
        CmsItemRevisionQuery::create()->filterByItemType($type->value)->filterByItemId($id)->delete();
    }

    /**
     * What would be served, or the reason it must not be: rendered again from
     * the document, like a page, so a component whose template changed since
     * the last save is published the way it looks today.
     */
    private function publishableHtml(CatalogueItemType $type, int $id, string $locale, CmsItemContent $content, bool $withCustomCode): string
    {
        $document = Document::fromJson($content->getDraftDocument());

        if (null === $document) {
            throw EmptyItemContentException::for($type, $id, $locale);
        }

        $html = $this->images->rewrite(
            $this->sanitizer->html($this->normalizer->html($this->renderer->render($document, $locale)), $withCustomCode),
        );

        if ($this->emptiness->isEmpty($html)) {
            throw EmptyItemContentException::for($type, $id, $locale);
        }

        // The theme draws the name of the item as the level 1 heading of its
        // page, above the document: the sections start at level 2, as on a
        // page of the CMS.
        $problems = $this->headings->check($html, underTitle: true);

        if ([] !== $problems && $this->settings->headingCheckBlocks()) {
            throw ItemHeadingStructureException::for($type, $id, $locale, $problems);
        }

        return $html;
    }

    private function contentFor(CatalogueItemType $type, int $id, string $locale): CmsItemContent
    {
        return $this->contents->find($type, $id, $locale)
            ?? (new CmsItemContent())->setItemType($type->value)->setItemId($id)->setLocale($locale);
    }

    private function mayPublishCustomCode(): bool
    {
        return $this->securityContext->isGranted(['ADMIN'], [CmsResources::CUSTOM_CODE], [], [AccessManager::UPDATE]);
    }

    private function snapshot(CmsItemContent $content, ConnectionInterface $connection): void
    {
        (new CmsItemRevision())
            ->setItemType($content->getItemType())
            ->setItemId($content->getItemId())
            ->setLocale($content->getLocale())
            ->setDocument($content->getPublishedDocument())
            ->setHtml($content->getPublishedHtml())
            ->setCreatedBy($this->securityContext->getAdminUser()?->getId())
            ->save($connection);

        $obsolete = CmsItemRevisionQuery::create()
            ->filterByItemType($content->getItemType())
            ->filterByItemId($content->getItemId())
            ->filterByLocale($content->getLocale())
            ->orderByCreatedAt(Criteria::DESC)
            ->orderById(Criteria::DESC)
            ->offset(self::REVISION_RETENTION)
            ->limit(1000)
            ->find($connection);

        foreach ($obsolete as $revision) {
            $revision->delete($connection);
        }
    }
}
