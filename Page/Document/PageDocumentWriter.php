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

namespace TheliaCMS\Page\Document;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use TheliaCMS\Model\CmsPageDocument;
use TheliaCMS\Model\CmsPageDocumentI18n;
use TheliaCMS\Model\CmsPageDocumentQuery;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Page\PageInvalidator;

/**
 * Every write to the files of a page: the row and the file on disk go
 * together, and the page is told it changed.
 */
final readonly class PageDocumentWriter
{
    /** Characters of `original_name`. */
    private const int ORIGINAL_NAME_MAX_LENGTH = 255;

    public function __construct(
        private PageDocumentStorage $storage,
        private PageInvalidator $invalidator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Attaches the files to the page, each on its own, after the documents it
     * has, in the order they were sent. A file of another format, too large or
     * broken on the way is refused and the others are attached.
     *
     * @param list<UploadedFile> $files
     */
    public function add(int $pageId, array $files): PageDocumentUpload
    {
        $this->assertPageExists($pageId);

        $stored = [];
        $refused = [];
        $position = $this->lastPosition($pageId);

        foreach ($files as $file) {
            $refusal = $this->refusalOf($file);

            if (null === $refusal) {
                try {
                    $stored[] = $this->attach($pageId, $file, ++$position);

                    continue;
                } catch (FileException) {
                    --$position;
                    $refusal = PageDocumentRefusal::Failed;
                }
            }

            $refused[] = ['name' => $file->getClientOriginalName(), 'reason' => $refusal];
        }

        if ([] !== $stored) {
            $this->invalidator->contentChanged($pageId);
        }

        return new PageDocumentUpload($stored, $refused);
    }

    /**
     * What the document is called, and what it holds, in one language. An
     * empty text is no text: the theme then names the file.
     */
    public function describe(int $documentId, string $locale, ?string $title, ?string $description): void
    {
        $document = $this->document($documentId);

        $document->setLocale($locale)
            ->setTitle($this->textOrNull($title))
            ->setDescription($this->textOrNull($description))
            ->save();

        $this->invalidator->contentChanged((int) $document->getPageId());
    }

    /**
     * Puts the documents of the page in the order given. The documents left
     * out keep their order, after them.
     *
     * @param list<int> $documentIds
     *
     * @throws \InvalidArgumentException when an id is not a document of the page
     */
    public function reorder(int $pageId, array $documentIds): void
    {
        $documents = [];

        foreach (CmsPageDocumentQuery::create()->filterByPageId($pageId)->orderByPosition()->orderById()->find() as $document) {
            $documents[(int) $document->getId()] = $document;
        }

        $ordered = array_values(array_unique(array_map('intval', $documentIds)));
        $foreign = array_diff($ordered, array_keys($documents));

        if ([] !== $foreign) {
            throw new \InvalidArgumentException(\sprintf('Not documents of the page #%d: %s.', $pageId, implode(', ', $foreign)));
        }

        $order = [...$ordered, ...array_values(array_diff(array_keys($documents), $ordered))];

        $connection = Propel::getConnection('TheliaMain');
        $connection->beginTransaction();

        try {
            foreach ($order as $index => $documentId) {
                $documents[$documentId]->setPosition($index + 1)->save($connection);
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        $this->invalidator->contentChanged($pageId);
    }

    /**
     * Deletes the document and its file. The file goes once the row is gone:
     * a row left without its file would be offered to visitors and answer 404.
     * A file that cannot be removed is logged, the document being gone.
     */
    public function delete(int $documentId): void
    {
        $document = $this->document($documentId);
        $pageId = (int) $document->getPageId();
        $file = (string) $document->getFile();

        $document->delete();

        // Before the file: the row is gone, so is the document, whatever the
        // disk answers next.
        $this->invalidator->contentChanged($pageId);
        $this->deleteFiles([$file]);
    }

    /**
     * Gives the copy of a page a copy of each document of the page, file
     * included: a row owns its file, so deleting a document of one page never
     * takes the file of the other. A document whose file is gone is not
     * copied, there is nothing to offer.
     *
     * Runs inside the transaction of the copy. The files copied before a
     * failure here are removed; a rollback of the caller after this returns
     * leaves its copies on disk, which is why the copy calls this last.
     */
    public function copyAll(int $fromPageId, int $toPageId, ConnectionInterface $connection): void
    {
        $copiedFiles = [];

        try {
            $documents = CmsPageDocumentQuery::create()
                ->filterByPageId($fromPageId)
                ->orderByPosition()
                ->orderById()
                ->find($connection);

            foreach ($documents as $document) {
                if (!$this->storage->exists((string) $document->getFile())) {
                    continue;
                }

                $file = $this->storage->copy((string) $document->getFile());
                $copiedFiles[] = $file;

                $copy = (new CmsPageDocument())
                    ->setPageId($toPageId)
                    ->setFile($file)
                    ->setOriginalName($document->getOriginalName())
                    ->setMimeType($document->getMimeType())
                    ->setSize($document->getSize())
                    ->setPosition($document->getPosition());

                foreach ($document->getCmsPageDocumentI18ns(null, $connection) as $translation) {
                    $copy->addCmsPageDocumentI18n(
                        (new CmsPageDocumentI18n())
                            ->setLocale($translation->getLocale())
                            ->setTitle($translation->getTitle())
                            ->setDescription($translation->getDescription())
                    );
                }

                $copy->save($connection);
            }
        } catch (\Throwable $throwable) {
            foreach ($copiedFiles as $file) {
                $this->storage->delete($file);
            }

            throw $throwable;
        }
    }

    /**
     * The stored files of the documents of a page, read before the page is
     * deleted for good: its rows go with it, the foreign key cascades.
     *
     * @return list<string>
     */
    public function filesOf(int $pageId, ?ConnectionInterface $connection = null): array
    {
        return array_map('strval', CmsPageDocumentQuery::create()
            ->filterByPageId($pageId)
            ->select(['File'])
            ->find($connection)
            ->toArray());
    }

    /**
     * Removes stored files from the disk, once the rows that named them are
     * gone for good.
     *
     * @param list<string> $files
     */
    public function deleteFiles(array $files): void
    {
        foreach ($files as $file) {
            // The rows are gone for good already: a file that stays is an orphan
            // to clean, not a reason to fail, nor to stop at the first one.
            try {
                $this->storage->delete($file);
            } catch (\Throwable $throwable) {
                $this->logger->error('Thelia CMS: the file of a deleted page document was not removed: {file} ({reason})', [
                    'file' => $file,
                    'reason' => $throwable->getMessage(),
                ]);
            }
        }
    }

    private function attach(int $pageId, UploadedFile $file, int $position): CmsPageDocument
    {
        $extension = PageDocumentFormat::extensionOf($file->getClientOriginalName());
        // Read before the move: the uploaded file is gone from its temporary
        // place afterwards.
        $mimeType = (string) $file->getMimeType();
        $name = $this->storage->nameFor($file->getClientOriginalName(), $extension);

        $this->storage->store($file, $name);

        try {
            $document = (new CmsPageDocument())
                ->setPageId($pageId)
                ->setFile($name)
                ->setOriginalName(mb_substr($file->getClientOriginalName(), 0, self::ORIGINAL_NAME_MAX_LENGTH))
                ->setMimeType($mimeType)
                ->setSize((int) filesize($this->storage->path($name)))
                ->setPosition($position);
            $document->save();
        } catch (\Throwable $throwable) {
            $this->storage->delete($name);

            throw $throwable;
        }

        return $document;
    }

    private function refusalOf(UploadedFile $file): ?PageDocumentRefusal
    {
        if (!$file->isValid()) {
            return \in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)
                ? PageDocumentRefusal::TooLarge
                : PageDocumentRefusal::Failed;
        }

        $leadingBytes = (string) @file_get_contents($file->getPathname(), length: 8);

        return PageDocumentFormat::accepts(PageDocumentFormat::extensionOf($file->getClientOriginalName()), $file->getMimeType(), $leadingBytes)
            ? null
            : PageDocumentRefusal::Format;
    }

    private function lastPosition(int $pageId): int
    {
        $last = CmsPageDocumentQuery::create()
            ->filterByPageId($pageId)
            ->orderByPosition(Criteria::DESC)
            ->findOne();

        return null === $last ? 0 : (int) $last->getPosition();
    }

    private function document(int $documentId): CmsPageDocument
    {
        return CmsPageDocumentQuery::create()->findPk($documentId)
            ?? throw new \InvalidArgumentException(\sprintf('No page document #%d.', $documentId));
    }

    private function assertPageExists(int $pageId): void
    {
        if (null === CmsPageQuery::create()->findPk($pageId)) {
            throw new \InvalidArgumentException(\sprintf('No CMS page #%d.', $pageId));
        }
    }

    private function textOrNull(?string $text): ?string
    {
        $text = null === $text ? '' : trim($text);

        return '' === $text ? null : $text;
    }
}
