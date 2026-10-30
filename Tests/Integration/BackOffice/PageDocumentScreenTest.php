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

namespace TheliaCMS\Tests\Integration\BackOffice;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\LangQuery;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Model\CmsPageDocumentQuery;
use TheliaCMS\Page\Document\PageDocumentRepository;
use TheliaCMS\Page\Document\PageDocumentStorage;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * The Documents tab of the edit screen of a page: files added, described in
 * the language being edited, put in order and deleted, each action written at
 * once, with the token and the right of the back office.
 *
 * The files land where the site keeps them, the test database being separate
 * but not the disk: every file a test stored is removed in tearDown().
 */
final class PageDocumentScreenTest extends AdminScreenTestCase
{
    private const string PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    private const array JSON_REQUEST = ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'HTTP_SEC_FETCH_SITE' => 'same-origin'];

    /** @var list<string> */
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

    public function testTheTabListsTheDocumentsOfThePageAndTheFormToAddMore(): void
    {
        $id = (int) $this->createPage('Documents tab')->getId();
        $empty = $this->screen($this->edit($id));

        self::assertSame(1, $empty->filter('[data-testid="cms-page-tab-documents"][data-bs-target="#tab-documents"]')->count());
        self::assertSame(1, $empty->filter('[data-testid="cms-page-documents-empty"]')->count());
        self::assertSame(1, $empty->filter('input[name="files[]"][multiple]')->count(), 'Several files at once.');
        self::assertGreaterThan(0, (int) $empty->filter('form[data-cms-document-upload]')->attr('data-max-size'), 'The limit of the server is given to the script.');

        $this->upload($id, [$this->file('Fiche technique.pdf', self::PDF), $this->file('Notes.txt', "Notes\n")]);
        $list = $this->screen($this->edit($id));
        $documents = $this->documents($id);

        self::assertCount(2, $documents);
        self::assertSame(2, $list->filter('[data-testid="cms-page-documents-list"] > li')->count());
        self::assertSame('Fiche technique', $list->filter('[data-testid="cms-page-document-title-'.$documents[0]->id.'"]')->text());
        self::assertSame('PDF', $list->filter('[data-testid="cms-page-document-extension-'.$documents[0]->id.'"]')->text());
        self::assertSame(\sprintf('/cms/documents/%d/%s', $documents[0]->id, $documents[0]->file), $list->filter('[data-testid="cms-page-document-open-'.$documents[0]->id.'"]')->attr('href'));
        self::assertSame('2', $list->filter('[data-testid="cms-page-tab-documents-count"]')->text());
    }

    public function testAPageNotSavedYetHasTheTabDisabled(): void
    {
        $tab = $this->screen('/admin/cms/pages/new')->filter('[data-testid="cms-page-tab-documents"]');

        self::assertSame(1, $tab->count());
        self::assertNotNull($tab->attr('disabled'));
        self::assertSame(0, $this->client->getCrawler()->filter('#tab-documents')->count(), 'No panel: there is no page to attach files to.');
    }

    public function testFilesAreAddedAndTheRefusedOneIsSaidWithoutCostingTheOther(): void
    {
        $id = (int) $this->createPage('Documents add')->getId();

        $this->upload($id, [$this->file('Tarifs.pdf', self::PDF), $this->file('page.html', '<html><script>alert(1)</script></html>')]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertStringEndsWith('#tab-documents', (string) $this->client->getResponse()->headers->get('Location'));
        self::assertSame(['Tarifs'], array_column($this->documents($id), 'title'));

        $screen = $this->screen($this->edit($id))->html();
        self::assertStringContainsString('page.html', $screen, 'The refused file is named.');
    }

    public function testTheScriptIsAnsweredInJsonFileByFile(): void
    {
        $id = (int) $this->createPage('Documents json')->getId();
        $token = $this->tokenOfTheScripts($this->screen($this->edit($id)));

        $this->client->request('POST', $this->url($id), ['_token' => $token], ['files' => [$this->file('Notes.txt', "Notes\n")]], self::JSON_REQUEST);
        $answer = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame('Notes.txt', $answer['stored'][0]['name']);
        self::assertSame([], $answer['refused']);

        $this->client->request('POST', $this->url($id), ['_token' => $token], ['files' => [$this->file('virus.exe', 'MZ')]], self::JSON_REQUEST);
        $answer = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame([], $answer['stored']);
        self::assertSame('virus.exe', $answer['refused'][0]['name']);
        self::assertNotSame('', $answer['refused'][0]['reason'], 'The script says why.');

        $this->client->request('POST', $this->url($id), ['_token' => $token], [], self::JSON_REQUEST);
        self::assertSame(422, $this->client->getResponse()->getStatusCode(), 'Nothing sent is not a success.');
    }

    public function testTheTextsAreWrittenInTheLanguageBeingEdited(): void
    {
        $id = (int) $this->createPage('Documents texts')->getId();
        $this->upload($id, [$this->file('Fiche.pdf', self::PDF)]);
        $documentId = $this->documents($id)[0]->id;
        $token = $this->tokenOfTheScripts($this->screen($this->edit($id)));
        $locale = $this->locale();
        $other = 'en_US' === $locale ? 'fr_FR' : 'en_US';
        $otherId = (int) LangQuery::create()->findOneByLocale($other)?->getId();

        self::assertSame(302, $this->send('POST', \sprintf('/admin/cms/pages/%d/documents/%d?edit_language_id=%d', $id, $documentId, $this->languageId()), ['_token' => $token, 'title' => '  Fiche produit  ', 'description' => 'À jour']));

        $repository = new PageDocumentRepository();
        self::assertSame('Fiche produit', $repository->forPage($id, $locale)[0]->title, 'Trimmed.');
        self::assertSame('Fiche', $repository->forPage($id, $other)[0]->title, 'The other language keeps the name of the file.');

        $this->send('POST', \sprintf('/admin/cms/pages/%d/documents/%d?edit_language_id=%d', $id, $documentId, $otherId), ['_token' => $token, 'title' => 'Data sheet', 'description' => '']);
        self::assertSame('Data sheet', $repository->forPage($id, $other)[0]->title);
        self::assertSame('Fiche produit', $repository->forPage($id, $locale)[0]->title);

        $this->send('POST', \sprintf('/admin/cms/pages/%d/documents/%d?edit_language_id=%d', $id, $documentId, $this->languageId()), ['_token' => $token, 'title' => '', 'description' => '']);
        self::assertSame('Fiche', $repository->forPage($id, $locale)[0]->title, 'Emptied: the file is named after its original name again.');

        $this->send('POST', \sprintf('/admin/cms/pages/%d/documents/%d?edit_language_id=%d', $id, $documentId, $this->languageId()), ['_token' => $token, 'title' => str_repeat('é', 256), 'description' => '']);
        self::assertSame('Fiche', $repository->forPage($id, $locale)[0]->title, 'A title longer than the column is refused, not cut.');
    }

    public function testTheDocumentsAreReorderedAndAnArrowCarriesTheOrderItGives(): void
    {
        $id = (int) $this->createPage('Documents order')->getId();
        $this->upload($id, [$this->file('A.pdf', self::PDF), $this->file('B.pdf', self::PDF), $this->file('C.pdf', self::PDF)]);
        $ids = array_column($this->documents($id), 'id');
        $screen = $this->screen($this->edit($id));
        $token = $this->tokenOfTheScripts($screen);

        $down = $screen->filter('[data-testid="cms-page-document-down-'.$ids[0].'"]');
        self::assertSame(1, $down->count());
        self::assertNotNull($screen->filter('[data-testid="cms-page-document-up-'.$ids[0].'"]')->attr('disabled'), 'The first one cannot go up.');

        $form = $down->ancestors()->filter('form')->first()->form();
        self::assertSame([$ids[1], $ids[0], $ids[2]], array_map('intval', $form->getPhpValues()['documents']));
        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));
        self::assertSame([$ids[1], $ids[0], $ids[2]], array_column($this->documents($id), 'id'));

        $this->send('POST', \sprintf('/admin/cms/pages/%d/documents/order', $id), ['_token' => $token, 'documents' => [$ids[2]]]);
        self::assertSame($ids[2], array_column($this->documents($id), 'id')[0], 'A partial list puts those first.');

        $other = (int) $this->createPage('Documents order other')->getId();
        $this->upload($other, [$this->file('Z.pdf', self::PDF)]);
        $foreign = $this->documents($other)[0]->id;
        $before = array_column($this->documents($id), 'id');

        self::assertSame(400, $this->send('POST', \sprintf('/admin/cms/pages/%d/documents/order', $id), ['_token' => $token, 'documents' => [$foreign, ...$before]]));
        self::assertSame($before, array_column($this->documents($id), 'id'), 'A document of another page is refused, the order stays.');
    }

    public function testADocumentIsDeletedWithItsFileAndOnlyThroughItsOwnPage(): void
    {
        $id = (int) $this->createPage('Documents delete')->getId();
        $other = (int) $this->createPage('Documents delete other')->getId();
        $this->upload($id, [$this->file('Mine.pdf', self::PDF)]);
        $this->upload($other, [$this->file('Theirs.pdf', self::PDF)]);
        $mine = $this->documents($id)[0];
        $theirs = $this->documents($other)[0];
        $token = $this->tokenOfTheScripts($this->screen($this->edit($id)));
        $path = (new PageDocumentStorage())->path($mine->file);

        self::assertFileExists($path);
        self::assertSame(404, $this->send('POST', \sprintf('/admin/cms/pages/%d/documents/%d/delete', $id, $theirs->id), ['_token' => $token]), 'The address names the page and the document.');
        self::assertCount(1, $this->documents($other));

        $dialog = $this->screen($this->edit($id))->filter('[data-testid="cms-page-document-delete-dialog-'.$mine->id.'"]');
        self::assertSame(1, $dialog->count(), 'The deletion is asked in a dialog of the screen.');
        self::assertSame(302, $this->send('POST', \sprintf('/admin/cms/pages/%d/documents/%d/delete', $id, $mine->id), ['_token' => $token]));
        self::assertSame([], $this->documents($id));
        self::assertFileDoesNotExist($path);
    }

    public function testUnknownAndBinnedPagesAnswer404(): void
    {
        $token = $this->tokenOfTheScripts($this->screen('/admin/cms/pages'));
        $binned = $this->createPage('Documents binned');
        $this->getService(\TheliaCMS\Page\Admin\CmsPageWriter::class)->moveToTrash($binned);

        foreach ([99999999, (int) $binned->getId()] as $id) {
            $this->client->request('POST', $this->url($id), ['_token' => $token], ['files' => [$this->file('Rien.pdf', self::PDF)]], self::SAME_SITE);
            self::assertSame(404, $this->client->getResponse()->getStatusCode());
            self::assertSame(404, $this->send('POST', \sprintf('/admin/cms/pages/%d/documents/order', $id), ['_token' => $token, 'documents' => []]));
        }

        self::assertSame(0, CmsPageDocumentQuery::create()->count());
    }

    public function testEveryActionIsRefusedWithoutTheTokenOfTheBackOffice(): void
    {
        $id = (int) $this->createPage('Documents token')->getId();
        $this->upload($id, [$this->file('Jeton.pdf', self::PDF)]);
        $document = $this->documents($id)[0];

        $actions = [
            [$this->url($id), []],
            [\sprintf('/admin/cms/pages/%d/documents/%d', $id, $document->id), ['title' => 'Pirate']],
            [\sprintf('/admin/cms/pages/%d/documents/order', $id), ['documents' => [$document->id]]],
            [\sprintf('/admin/cms/pages/%d/documents/%d/delete', $id, $document->id), []],
        ];

        foreach ($actions as [$url, $body]) {
            self::assertSame(403, $this->send('POST', $url, $body), $url.' without the token.');
            self::assertSame(403, $this->send('POST', $url, $body + ['_token' => 'not-the-token']), $url.' with a wrong token.');
        }

        self::assertSame(['Jeton'], array_column($this->documents($id), 'title'));
    }

    public function testTheRightsOfThePageGovernTheTab(): void
    {
        $id = (int) $this->createPage('Documents rights')->getId();
        $this->upload($id, [$this->file('Droits.pdf', self::PDF)]);
        $document = $this->documents($id)[0];
        $factory = new FixtureFactory($this->getPropelConnection());

        $this->logInAs($factory->restrictedAdmin([CmsResources::PAGE => [AccessManager::VIEW]]));
        $screen = $this->screen($this->edit($id));
        $token = $this->tokenOfTheScripts($screen);
        self::assertSame(1, $screen->filter('[data-testid="cms-page-document-'.$document->id.'"]')->count(), 'Reading is allowed.');
        self::assertSame(0, $screen->filter('input[name="files[]"], [data-testid^="cms-page-document-delete-open-"], input[name="documents[]"]')->count(), 'No form for what the administrator cannot do.');
        self::assertSame(403, $this->send('POST', $this->url($id), ['_token' => $token]));
        self::assertSame(403, $this->send('POST', \sprintf('/admin/cms/pages/%d/documents/%d', $id, $document->id), ['_token' => $token, 'title' => 'Lecteur']));
        self::assertSame(403, $this->send('POST', \sprintf('/admin/cms/pages/%d/documents/order', $id), ['_token' => $token, 'documents' => [$document->id]]));
        self::assertSame(403, $this->send('POST', \sprintf('/admin/cms/pages/%d/documents/%d/delete', $id, $document->id), ['_token' => $token]));
        self::assertCount(1, $this->documents($id));

        $this->logInAs($factory->restrictedAdmin([CmsResources::PAGE => [AccessManager::VIEW, AccessManager::UPDATE]]));
        $screen = $this->screen($this->edit($id));
        $token = $this->tokenOfTheScripts($screen);
        self::assertSame(1, $screen->filter('input[name="files[]"]')->count());
        self::assertSame(0, $screen->filter('[data-testid^="cms-page-document-delete-open-"]')->count(), 'Updating is not deleting.');
        self::assertSame(403, $this->send('POST', \sprintf('/admin/cms/pages/%d/documents/%d/delete', $id, $document->id), ['_token' => $token]));
        self::assertCount(1, $this->documents($id));
    }

    private function edit(int $id): string
    {
        return \sprintf('/admin/cms/pages/%d?edit_language_id=%d', $id, $this->languageId());
    }

    private function url(int $id): string
    {
        return \sprintf('/admin/cms/pages/%d/documents?edit_language_id=%d', $id, $this->languageId());
    }

    private function languageId(): int
    {
        return (int) LangQuery::create()->findOneByLocale($this->locale())?->getId();
    }

    /**
     * @param list<UploadedFile> $files
     */
    private function upload(int $id, array $files): void
    {
        $token = $this->tokenOfTheScripts($this->screen($this->edit($id)));

        $this->client->request('POST', $this->url($id), ['_token' => $token], ['files' => $files], self::SAME_SITE);
    }

    /**
     * @return list<\TheliaCMS\Page\Document\PageDocument>
     */
    private function documents(int $id): array
    {
        return (new PageDocumentRepository())->forPage($id, $this->locale());
    }

    private function file(string $name, string $content): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'cms-page-document-');
        file_put_contents($path, $content);
        $this->uploads[] = $path;

        return new UploadedFile($path, $name, null, \UPLOAD_ERR_OK, true);
    }
}
