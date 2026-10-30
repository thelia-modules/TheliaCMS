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

use Symfony\Component\HttpFoundation\File\UploadedFile;
use TheliaCMS\Model\CmsPageDocumentQuery;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Page\Document\PageDocumentRefusal;
use TheliaCMS\Page\Document\PageDocumentRepository;
use TheliaCMS\Page\Document\PageDocumentStorage;
use TheliaCMS\Page\Document\PageDocumentWriter;
use TheliaCMS\Page\TrashPurger;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * The files of a page: one row owns one file on disk, so whatever writes a
 * row writes or removes its file with it, a copied page included.
 *
 * The files land where the site keeps them, the test database being separate
 * but not the disk: every file a test stored is removed in tearDown().
 */
final class PageDocumentTest extends CmsIntegrationTestCase
{
    /** @var list<string> paths of the temporary uploads */
    private array $uploads = [];

    protected function tearDown(): void
    {
        $storage = $this->storage();

        foreach (CmsPageDocumentQuery::create()->select(['File'])->find()->toArray() as $file) {
            $storage->delete((string) $file);
        }

        foreach ($this->uploads as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function testFilesAreAttachedAfterTheOnesThePageHasInTheOrderSent(): void
    {
        $page = $this->createPage('Page à fichiers');
        $writer = $this->documents();

        $first = $writer->add((int) $page->getId(), [$this->upload('Tarifs 2026.pdf', $this->pdf())]);
        $second = $writer->add((int) $page->getId(), [
            $this->upload('Notice.txt', "Notice\n"),
            $this->upload('Archive.zip', $this->zip()),
        ]);

        self::assertCount(1, $first->stored);
        self::assertCount(2, $second->stored);
        self::assertSame([], $second->refused);

        $documents = $this->repository()->forPage((int) $page->getId(), $this->locale());

        self::assertSame(['Tarifs 2026', 'Notice', 'Archive'], array_map(static fn ($document): string => $document->title, $documents));
        self::assertSame('application/pdf', $documents[0]->mimeType);
        self::assertSame('pdf', $documents[0]->extension);
        self::assertSame(\strlen($this->pdf()), $documents[0]->size);
        self::assertMatchesRegularExpression('/^tarifs-2026-[0-9a-f]{8}\.pdf$/D', $documents[0]->file);
        self::assertFileExists($this->storage()->path($documents[0]->file));
    }

    public function testAFileOfAnotherFormatIsRefusedAndTheOthersAreAttached(): void
    {
        $page = $this->createPage('Page refusant');

        $upload = $this->documents()->add((int) $page->getId(), [
            $this->upload('page.html', '<html><body>x</body></html>'),
            $this->upload('fiche.pdf', '<html><body>x</body></html>'),
            $this->upload('script.txt', '<?php echo 1;'),
            $this->upload('fiche.pdf', $this->pdf()),
        ]);

        self::assertCount(1, $upload->stored);
        self::assertSame(
            [['page.html', PageDocumentRefusal::Format], ['fiche.pdf', PageDocumentRefusal::Format], ['script.txt', PageDocumentRefusal::Format]],
            array_map(static fn (array $refused): array => [$refused['name'], $refused['reason']], $upload->refused),
        );
    }

    public function testAFileTheServerCutIsTooLarge(): void
    {
        $page = $this->createPage('Page trop lourde');

        $upload = $this->documents()->add((int) $page->getId(), [$this->upload('lourd.pdf', '', \UPLOAD_ERR_INI_SIZE)]);

        self::assertSame(PageDocumentRefusal::TooLarge, $upload->refused[0]['reason']);
    }

    public function testATitleIsWrittenPerLanguageWithoutFallback(): void
    {
        $page = $this->createPage('Page décrite');
        $document = $this->documents()->add((int) $page->getId(), [$this->upload('fiche.pdf', $this->pdf())])->stored[0];

        $this->documents()->describe((int) $document->getId(), $this->locale(), ' Fiche produit ', 'Toutes les cotes');
        $this->documents()->describe((int) $document->getId(), 'xx_XX', '', '');

        $repository = $this->repository();
        $here = $repository->forPage((int) $page->getId(), $this->locale())[0];
        $elsewhere = $repository->forPage((int) $page->getId(), 'xx_XX')[0];

        self::assertSame('Fiche produit', $here->title);
        self::assertSame('Toutes les cotes', $here->description);
        self::assertSame('fiche', $elsewhere->title, 'The name of the file, not the title of another language.');
        self::assertNull($elsewhere->description);
    }

    public function testReorderingFollowsTheIdsAndRefusesADocumentOfAnotherPage(): void
    {
        $page = $this->createPage('Page à trier');
        $other = $this->createPage('Autre page');
        $stored = $this->documents()->add((int) $page->getId(), [
            $this->upload('a.txt', "a\n"),
            $this->upload('b.txt', "b\n"),
            $this->upload('c.txt', "c\n"),
        ])->storedIds();
        $foreign = $this->documents()->add((int) $other->getId(), [$this->upload('d.txt', "d\n")])->storedIds();

        $this->documents()->reorder((int) $page->getId(), [$stored[2], $stored[0]]);

        self::assertSame(
            [$stored[2], $stored[0], $stored[1]],
            array_map(static fn ($document): int => $document->id, $this->repository()->forPage((int) $page->getId(), $this->locale())),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->documents()->reorder((int) $page->getId(), [$foreign[0], $stored[0]]);
    }

    public function testDeletingADocumentRemovesItsFile(): void
    {
        $page = $this->createPage('Page allégée');
        $document = $this->documents()->add((int) $page->getId(), [$this->upload('fiche.pdf', $this->pdf())])->stored[0];
        $path = $this->storage()->path((string) $document->getFile());

        $this->documents()->delete((int) $document->getId());

        self::assertNull(CmsPageDocumentQuery::create()->findPk($document->getId()));
        self::assertFileDoesNotExist($path);
    }

    public function testADuplicatedPageGetsCopiesOfTheFiles(): void
    {
        $page = $this->createPage('Page à copier');
        $document = $this->documents()->add((int) $page->getId(), [$this->upload('fiche.pdf', $this->pdf())])->stored[0];
        $this->documents()->describe((int) $document->getId(), $this->locale(), 'Fiche', null);

        $copy = $this->writer()->duplicate($page, $this->locale(), '(copie)');

        $copied = $this->repository()->forPage((int) $copy->getId(), $this->locale());
        $storage = $this->storage();

        self::assertCount(1, $copied);
        self::assertSame('Fiche', $copied[0]->title);
        self::assertNotSame($document->getFile(), $copied[0]->file, 'A row owns its file.');
        self::assertFileEquals($storage->path((string) $document->getFile()), $storage->path($copied[0]->file));

        $this->documents()->delete((int) $document->getId());

        self::assertFileExists($storage->path($copied[0]->file), 'Deleting the document of the page leaves the one of the copy.');
    }

    public function testThePurgeOfTheBinRemovesTheFilesAndTheBinAloneKeepsThem(): void
    {
        $page = $this->createPage('Page jetée');
        $document = $this->documents()->add((int) $page->getId(), [$this->upload('fiche.pdf', $this->pdf())])->stored[0];
        $path = $this->storage()->path((string) $document->getFile());

        $this->writer()->moveToTrash($page);

        self::assertFileExists($path, 'A binned page can be restored, its files with it.');

        CmsPageQuery::create()->findPk($page->getId())?->setDeletedAt(new \DateTimeImmutable('-10 years'))->save();
        $this->getService(TrashPurger::class)->purge();

        self::assertNull(CmsPageQuery::create()->findPk($page->getId()));
        self::assertSame(0, CmsPageDocumentQuery::create()->filterByPageId($page->getId())->count());
        self::assertFileDoesNotExist($path);
    }

    /**
     * Built here: with a single consumer, or none yet, neither is left in the
     * test container to be fetched.
     */
    private function storage(): PageDocumentStorage
    {
        return new PageDocumentStorage();
    }

    private function repository(): PageDocumentRepository
    {
        return new PageDocumentRepository();
    }

    private function documents(): PageDocumentWriter
    {
        return $this->getService(PageDocumentWriter::class);
    }

    private function upload(string $name, string $content, int $error = \UPLOAD_ERR_OK): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cms-page-document-');
        file_put_contents($path, $content);
        $this->uploads[] = $path;

        return new UploadedFile($path, $name, null, $error, test: true);
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
    }

    private function zip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cms-page-document-zip-');
        $this->uploads[] = $path;
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('notice.txt', "Notice\n");
        $zip->close();

        return (string) file_get_contents($path);
    }
}
