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


namespace TheliaCMS\Tests\Integration\Twig;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageDocumentQuery;
use TheliaCMS\Page\Document\PageDocumentStorage;
use TheliaCMS\Page\Document\PageDocumentWriter;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\Twig\CmsExtension;

/**
 * `cms_page_documents_block()`: the list of the files of a page, drawn by the
 * module unless the theme ships `cms-page-documents.html.twig`.
 *
 * Called from the render of a Twig template, as a page template does, which is
 * a nested render of the theme parser.
 */
final class PageDocumentsBlockTest extends CmsIntegrationTestCase
{
    /** @var list<string> paths of the temporary uploads */
    private array $uploads = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAnInstalledFrontTheme();
    }

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

    public function testAPageWithoutDocumentDrawsNothing(): void
    {
        $page = $this->createPage('Page sans fichier');

        self::assertSame('', $this->block((int) $page->getId()));
        self::assertSame('', $this->block(99_999_999));
        self::assertSame('', $this->block(null));
    }

    public function testTheDocumentsAreListedWithTheirTitleTypeAndSize(): void
    {
        $page = $this->createPage('Page à fichiers');
        $this->givenDocument($page, 'Notice produit.pdf', $this->pdf());
        $this->givenDocument($page, 'Tarifs.zip', 'PK'."\x03\x04".str_repeat('x', 2000));

        $html = $this->block((int) $page->getId(), 'en_US');

        self::assertSame(1, substr_count($html, '<section class="cms-documents"'));
        self::assertStringContainsString('>Documents to download</h2>', $html);
        self::assertSame(2, substr_count($html, 'cms-documents__item'));
        self::assertStringContainsString('>Notice produit</a>', $html);
        self::assertStringContainsString('PDF · ', $html);
        self::assertStringContainsString('ZIP · 2 KB', $html);
        // The pdf opens in the tab, the archive is saved.
        self::assertSame(1, substr_count($html, ' download>'));
    }

    public function testTheHeadingFollowsTheLanguageAskedFor(): void
    {
        $page = $this->createPage('Page à titre');
        $this->givenDocument($page, 'Notice.pdf', $this->pdf());

        self::assertStringContainsString('>Documents à télécharger</h2>', $this->block((int) $page->getId(), 'fr_FR'));
    }

    public function testATemplateOfTheThemeTakesTheListOver(): void
    {
        $page = $this->createPage('Page au thème');
        $this->givenDocument($page, 'Notice.pdf', $this->pdf());

        $directory = \dirname(__DIR__, 3).'/templates/frontOffice/default';
        $file = $directory.'/cms-page-documents.html.twig';
        $created = !is_dir($directory);
        $created && mkdir($directory, 0o777, true);
        file_put_contents($file, '<div class="theme-docs">{% for d in cms_documents %}[{{ d.title }}|{{ d.type }}]{% endfor %}</div>');

        try {
            $this->registerForTheDefaultTheme($directory);

            self::assertSame('<div class="theme-docs">[Notice|PDF]</div>', $this->block((int) $page->getId(), 'en_US'));
        } finally {
            unlink($file);
            $created && rmdir($directory);
        }
    }

    private function block(?int $pageId, ?string $locale = null): string
    {
        $extension = $this->getService(CmsExtension::class);

        foreach ($extension->getFunctions() as $function) {
            if ('cms_page_documents_block' === $function->getName()) {
                self::assertSame(['html'], $function->getSafe(new \Twig\Node\Node()), 'The markup is not escaped by Twig.');

                return (string) ($function->getCallable())($pageId, $locale);
            }
        }

        self::fail('The extension registers no "cms_page_documents_block" function, so no theme can ask.');
    }

    private function registerForTheDefaultTheme(string $directory): void
    {
        $renderer = (new \ReflectionProperty($this->getService(CmsExtension::class), 'templates'))->getValue($this->getService(CmsExtension::class));
        $parser = (new \ReflectionMethod($renderer, 'parser'))->invoke($renderer);
        $property = new \ReflectionProperty($parser, 'templateDirectories');
        $registry = $property->getValue($parser);
        $registry[\Thelia\Core\Template\TemplateDefinition::FRONT_OFFICE]['default'][] = $directory;
        $property->setValue($parser, $registry);
        $parser->getTwig()->getLoader()->addPath($directory);
    }

    private function givenDocument(CmsPage $page, string $name, string $content): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cms-page-document-');
        file_put_contents($path, $content);
        $this->uploads[] = $path;

        $result = $this->getService(PageDocumentWriter::class)->add((int) $page->getId(), [new UploadedFile($path, $name, null, \UPLOAD_ERR_OK, test: true)]);
        self::assertSame([], $result->refused, 'The file of the scenario is accepted.');
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
    }
}
