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

namespace TheliaCMS\Tests\Integration\Front;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Model\Lang;
use Thelia\Test\WebIntegrationTestCase;
use TheliaCMS\Content\Document;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageDocumentQuery;
use TheliaCMS\Page\Admin\CmsPageWriter;
use TheliaCMS\Page\Admin\PageDraft;
use TheliaCMS\Page\Document\PageDocument;
use TheliaCMS\Page\Document\PageDocumentRepository;
use TheliaCMS\Page\Document\PageDocumentStorage;
use TheliaCMS\Page\Document\PageDocumentWriter;
use Twig\Environment;

/**
 * The files of a page requested over HTTP the way a visitor does, and listed
 * from a template the way a theme does.
 *
 * The files land where the site keeps them, the test database being separate
 * but not the disk: every file a test stored is removed in tearDown().
 */
final class PageDocumentDownloadTest extends WebIntegrationTestCase
{
    /** @var list<string> paths of the temporary uploads */
    private array $uploads = [];

    protected function tearDown(): void
    {
        $storage = new PageDocumentStorage();

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

    public function testAPdfIsShownByTheBrowserUnderItsOwnType(): void
    {
        $document = $this->givenDocument($this->createPage('Page à fiche'), 'Fiche technique Été.pdf', $this->pdf());

        $response = $this->download($document);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
        self::assertStringContainsString("filename*=utf-8''Fiche%20technique%20%C3%89t%C3%A9.pdf", (string) $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        // Five minutes, then the browser asks again: a 304 while the document
        // is there, a 404 once it is gone (see the controller).
        self::assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('must-revalidate', (string) $response->headers->get('Cache-Control'));
        self::assertStringNotContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    public function testATextIsShownAsUtf8PlainText(): void
    {
        $document = $this->givenDocument($this->createPage('Page à note'), 'Note.txt', "Été\n");

        $response = $this->download($document);

        self::assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
    }

    public function testAZipIsSavedNotShown(): void
    {
        $document = $this->givenDocument($this->createPage('Page à archive'), 'Pack.zip', $this->zip());

        $response = $this->download($document);

        self::assertSame('application/zip', $response->headers->get('Content-Type'));
        self::assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
    }

    /**
     * The back office links the files of the page it is writing, and a page
     * taken offline keeps its files at the same addresses.
     */
    public function testAPageNeverPublishedOrOfflineServesItsFiles(): void
    {
        $draft = $this->createPage('Brouillon à fiche', published: false);
        $offline = $this->createPage('Page hors ligne à fiche');
        $offline->setVisible(0)->save();

        self::assertSame(200, $this->download($this->givenDocument($draft, 'Brouillon.pdf', $this->pdf()))->getStatusCode());
        self::assertSame(200, $this->download($this->givenDocument($offline, 'Hors-ligne.pdf', $this->pdf()))->getStatusCode());
    }

    public function testAPageInTheTrashServesNothing(): void
    {
        $page = $this->createPage('Page à la corbeille');
        $document = $this->givenDocument($page, 'Corbeille.pdf', $this->pdf());

        $page->setDeletedAt(new \DateTimeImmutable())->save();

        self::assertSame(404, $this->download($document)->getStatusCode());
    }

    public function testAnIdAndANameOfTwoRowsAreNotAnAddress(): void
    {
        $page = $this->createPage('Page à deux fichiers');
        $first = $this->givenDocument($page, 'Premier.pdf', $this->pdf());
        $second = $this->givenDocument($page, 'Second.pdf', $this->pdf());

        $this->client->request('GET', \sprintf('/cms/documents/%d/%s', $first->id, $second->file));

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testAnUnknownRowAnswersNotFound(): void
    {
        $document = $this->givenDocument($this->createPage('Page à effacer'), 'Effacé.pdf', $this->pdf());

        $this->client->request('GET', \sprintf('/cms/documents/%d/%s', $document->id + 100000, $document->file));

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testANameOutOfShapeAnswersNotFound(): void
    {
        $document = $this->givenDocument($this->createPage('Page à nom'), 'Nom.pdf', $this->pdf());

        foreach (['..%2F..%2F.env', 'nom-0123abcd.html', 'nom.pdf'] as $file) {
            $this->client->request('GET', \sprintf('/cms/documents/%d/%s', $document->id, $file));

            self::assertSame(404, $this->client->getResponse()->getStatusCode(), $file);
        }
    }

    public function testARowWhoseFileIsGoneAnswersNotFound(): void
    {
        $document = $this->givenDocument($this->createPage('Page au fichier perdu'), 'Perdu.pdf', $this->pdf());
        (new PageDocumentStorage())->delete($document->file);

        self::assertSame(404, $this->download($document)->getStatusCode());
    }

    /**
     * What a theme prints: the order the editor set, the title of the language
     * asked (the name of the file when none was written, never another
     * language's), and an address that serves the file.
     */
    public function testTheTemplateFunctionListsTheDocumentsOfTheLanguageServed(): void
    {
        $page = $this->createPage('Page à deux langues');
        $first = $this->givenDocument($page, 'Notice produit.pdf', $this->pdf());
        $second = $this->givenDocument($page, 'Tarifs.zip', $this->zip());
        $writer = $this->getService(PageDocumentWriter::class);
        $writer->describe($first->id, 'fr_FR', 'Notice', 'Mode d\'emploi');
        $writer->describe($first->id, 'en_US', 'Manual', null);
        $writer->reorder((int) $page->getId(), [$second->id, $first->id]);

        $french = $this->listed($page, 'fr_FR');
        $english = $this->listed($page, 'en_US');

        self::assertSame(['Tarifs', 'Notice'], array_column($french, 'title'));
        self::assertSame(['Tarifs', 'Manual'], array_column($english, 'title'), 'A language with nothing written shows the name of the file, not the other language.');
        self::assertSame("Mode d'emploi", $french[1]['description']);
        self::assertNull($english[1]['description']);
        self::assertSame(['zip', 'pdf'], array_column($french, 'extension'));
        self::assertSame(['Tarifs.zip', 'Notice produit.pdf'], array_column($french, 'originalName'));
        self::assertSame(\sprintf('/cms/documents/%d/%s', $first->id, $first->file), $french[1]['url']);

        $this->client->request('GET', $french[1]['url']);
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'The address listed serves the file.');
    }

    public function testThePageIsGivenAsAnIdOrAsItself(): void
    {
        $page = $this->createPage('Page à id');
        $this->givenDocument($page, 'Un.pdf', $this->pdf());
        $twig = $this->getService(Environment::class);

        self::assertSame('1', $twig->createTemplate('{{ cms_page_documents(id)|length }}')->render(['id' => (int) $page->getId()]));
        self::assertSame('1', $twig->createTemplate('{{ cms_page_documents(id)|length }}')->render(['id' => (string) $page->getId()]));
    }

    public function testAnUnknownPageOrOneInTheTrashHasNoDocument(): void
    {
        $page = $this->createPage('Page à vider');
        $this->givenDocument($page, 'Vide.pdf', $this->pdf());
        $twig = $this->getService(Environment::class);

        self::assertSame('0', $twig->createTemplate('{{ cms_page_documents(id)|length }}')->render(['id' => 99999999]));
        self::assertSame('0', $twig->createTemplate('{{ cms_page_documents(id)|length }}')->render(['id' => null]));

        $page->setDeletedAt(new \DateTimeImmutable())->save();

        self::assertSame('0', $twig->createTemplate('{{ cms_page_documents(id)|length }}')->render(['id' => (int) $page->getId()]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listed(CmsPage $page, string $locale): array
    {
        $twig = $this->getService(Environment::class);
        $json = $twig->createTemplate('{{ cms_page_documents(id, locale)|map(d => {title: d.title, description: d.description, url: d.url, extension: d.extension, size: d.size, originalName: d.originalName})|json_encode|raw }}')
            ->render(['id' => (int) $page->getId(), 'locale' => $locale]);

        return json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
    }

    private function download(PageDocument $document): \Symfony\Component\HttpFoundation\Response
    {
        $this->client->request('GET', \sprintf('/cms/documents/%d/%s', $document->id, $document->file));

        return $this->client->getResponse();
    }

    private function givenDocument(CmsPage $page, string $name, string $content): PageDocument
    {
        $path = tempnam(sys_get_temp_dir(), 'cms-page-document-');
        file_put_contents($path, $content);
        $this->uploads[] = $path;

        $result = $this->getService(PageDocumentWriter::class)->add((int) $page->getId(), [new UploadedFile($path, $name, null, \UPLOAD_ERR_OK, test: true)]);
        self::assertSame([], $result->refused, 'The file of the scenario is accepted.');

        $locale = Lang::getDefaultLanguage()->getLocale();

        return array_values(array_filter(
            (new PageDocumentRepository())->forPage((int) $page->getId(), $locale),
            static fn (PageDocument $document): bool => $document->originalName === $name,
        ))[0];
    }

    private function createPage(string $title, bool $published = true): CmsPage
    {
        $locale = Lang::getDefaultLanguage()->getLocale();
        $writer = $this->getService(CmsPageWriter::class);
        \assert($writer instanceof CmsPageWriter);

        $page = new CmsPage();
        $page->setParent(0)->setPosition(0)->setVisible(1)->setPageType('default');

        $writer->saveDraft($page, $locale, new PageDraft(title: $title));
        $writer->saveContent($page, $locale, Document::richText(\sprintf('<h1>%s</h1>', htmlspecialchars($title))));

        if ($published) {
            $writer->publish($page, $locale);
        }

        return $page;
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
