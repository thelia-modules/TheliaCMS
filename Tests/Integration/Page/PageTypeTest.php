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

use Psr\Log\NullLogger;
use TheliaCMS\Http\CachePurger;
use TheliaCMS\Http\CachePurgerInterface;
use TheliaCMS\Http\CacheTags;
use TheliaCMS\ImportExport\ImportOptions;
use TheliaCMS\ImportExport\SiteDocument;
use TheliaCMS\ImportExport\SiteExporter;
use TheliaCMS\ImportExport\SiteImporter;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Model\Map\CmsPageTableMap;
use TheliaCMS\Page\Admin\CmsPageWriter;
use TheliaCMS\Page\Admin\PageDraft;
use TheliaCMS\Page\CmsPageRenderer;
use TheliaCMS\Page\PageTemplateResolver;
use TheliaCMS\Page\PageTypeCode;
use TheliaCMS\Page\PageTypeInUseException;
use TheliaCMS\Page\PageTypeRepository;
use TheliaCMS\Page\PageTypeWriter;
use TheliaCMS\Page\PublishedPage;
use TheliaCMS\Page\PublishedPageRepository;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * The type of a page: the list the site keeps, the template it picks, and the
 * ways a page gets one — the form, a duplicate, an import, a saved template.
 */
final class PageTypeTest extends CmsIntegrationTestCase
{
    private const string MODULE_TEMPLATE_DIRECTORY = __DIR__.'/../../../templates/front';

    public function testTheDefaultTypeComesFirstAndTheOthersInOrder(): void
    {
        $this->typeWriter()->add('zz-news');
        $this->typeWriter()->add('aa-recipe');

        $codes = $this->types()->codes();

        self::assertSame(PageTypeCode::DEFAULT, $codes[0]);
        self::assertContains('full-width', $codes, 'The former layouts are types of their own.');
        self::assertContains('landing', $codes);
        self::assertLessThan(array_search('zz-news', $codes, true), array_search('aa-recipe', $codes, true));
    }

    public function testAnInvalidCodeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->typeWriter()->add('../recipe');
    }

    public function testATypeABinnedPageStillHasCannotBeDeleted(): void
    {
        $this->typeWriter()->add('recipe');
        $page = $this->createPage('Gratin de pâtes');
        $page->setPageType('recipe')->save();
        $this->writer()->moveToTrash($page);

        try {
            $this->typeWriter()->delete('recipe');
            self::fail('A type a binned page has was deleted: restoring the page brings back a type the site does not have.');
        } catch (PageTypeInUseException $exception) {
            self::assertSame(1, $exception->pages);
        }

        self::assertTrue($this->types()->exists('recipe'));
    }

    public function testATypeNoPageHasIsDeleted(): void
    {
        $this->typeWriter()->add('recipe');

        $this->typeWriter()->delete('recipe');

        self::assertFalse($this->types()->exists('recipe'));
    }

    public function testTheDefaultTypeIsNeverDeleted(): void
    {
        $this->expectException(\LogicException::class);

        $this->typeWriter()->delete(PageTypeCode::DEFAULT);
    }

    /**
     * The column ignores case: `DEFAULT` must not read as the default type,
     * nor reach its row.
     */
    public function testACodeIsComparedAsACode(): void
    {
        self::assertFalse($this->types()->exists('DEFAULT'));
        self::assertFalse($this->types()->exists('default '));

        $this->expectException(\LogicException::class);
        $this->typeWriter()->delete('DEFAULT');
    }

    public function testARowWrittenByHandThatIsNotACodeIsNotOffered(): void
    {
        $this->getPropelConnection()->exec("INSERT INTO cms_page_type (code) VALUES ('a/b')");

        self::assertNotContains('a/b', $this->types()->codes());
    }

    public function testThePagesOfEachTypeAreCountedTheBinIncluded(): void
    {
        $this->typeWriter()->add('recipe');
        $before = $this->types()->pageCountsByType()['recipe'] ?? 0;
        $binned = $this->createPage('Gratin de pâtes');
        $binned->setPageType('recipe')->save();
        $this->writer()->moveToTrash($binned);
        $this->createPage('Tarte')->setPageType('recipe')->save();

        self::assertSame($before + 2, $this->types()->pageCountsByType()['recipe']);
    }

    public function testThePublishedPageCarriesItsType(): void
    {
        $page = $this->createPage('Gratin de pâtes');
        $page->setPageType('landing')->save();

        $published = $this->published($page);

        self::assertSame('landing', $published->pageType);
    }

    public function testACodeWrittenByHandThatIsNotACodeReadsAsTheDefaultType(): void
    {
        $page = $this->createPage('Page modifiée à la main');
        $this->getPropelConnection()->exec(\sprintf("UPDATE cms_page SET page_type = '../secret' WHERE id = %d", $page->getId()));
        CmsPageTableMap::clearInstancePool();

        self::assertSame(PageTypeCode::DEFAULT, $this->published($page)->pageType);
    }

    public function testAPageIsRenderedWithTheTemplateOfItsType(): void
    {
        $this->useAnInstalledFrontTheme();
        $code = 'test-'.bin2hex(random_bytes(4));
        $file = self::MODULE_TEMPLATE_DIRECTORY.'/cmspage-'.$code.'.html.twig';
        file_put_contents($file, '<main data-type-template="{{ cms_page.pageType }}">{{ cms_page.html|raw }}</main>');

        try {
            $page = $this->createPage('Gratin de pâtes', html: '<p>Recette</p>');
            $page->setPageType($code)->save();

            $html = $this->getService(CmsPageRenderer::class)->render($this->published($page));

            self::assertStringContainsString(\sprintf('<main data-type-template="%s"><p>Recette</p></main>', $code), $html);
        } finally {
            unlink($file);
        }
    }

    public function testAPageOfATypeWithoutTemplateKeepsTheUsualOne(): void
    {
        // Resolved rather than rendered: the usual template extends the base
        // layout of the theme, whose assets a test run does not build.
        $this->useAnInstalledFrontTheme();

        $template = $this->getService(PageTemplateResolver::class)->resolve('type-without-template');

        self::assertTrue($template->source->isFallback());
        self::assertStringEndsWith('cmspage.html.twig', $template->name);
    }

    public function testADuplicatedPageKeepsItsType(): void
    {
        $page = $this->createPage('Gratin de pâtes');
        $page->setPageType('landing')->save();

        $copy = $this->writer()->duplicate($page, $this->locale(), '(copie)');

        self::assertSame('landing', $copy->getPageType());
    }

    public function testSavingAPageDropsItFromTheSharedCache(): void
    {
        $page = $this->createPage('Gratin de pâtes');
        $purger = new class implements CachePurgerInterface {
            /** @var list<string> */
            public array $purged = [];

            public function purge(array $tags): void
            {
                array_push($this->purged, ...$tags);
            }
        };

        $page->setPageType('landing');
        $this->writerPurgingWith($purger)->saveDraft($page, $this->locale(), new PageDraft(title: 'Gratin de pâtes'));

        self::assertSame(CacheTags::forPage((int) $page->getId()), $purger->purged);
    }

    public function testAFileWrittenBeforePageTypesKeepsTheLayoutOfItsPages(): void
    {
        $page = $this->createPage('Gratin de pâtes');
        $document = $this->exportedWithType($page, legacyLayout: 'landing');

        $report = $this->getService(SiteImporter::class)->import(SiteDocument::fromArray($document), new ImportOptions(replace: true));

        self::assertSame([], $report->warnings());
        self::assertSame('landing', $this->reloaded($page)->getPageType());
    }

    public function testATypeTheSiteDoesNotHaveIsCreatedByTheImport(): void
    {
        $page = $this->createPage('Gratin de pâtes');
        $document = $this->exportedWithType($page, pageType: 'recipe');

        $report = $this->getService(SiteImporter::class)->import(SiteDocument::fromArray($document), new ImportOptions(replace: true));

        self::assertSame('recipe', $this->reloaded($page)->getPageType());
        self::assertTrue($this->types()->exists('recipe'));
        self::assertSame(['Page type "recipe" did not exist on this site: it was created.'], $report->warnings());
    }

    public function testAnImportedTypeThatIsNotACodeIsTheDefaultType(): void
    {
        $page = $this->createPage('Gratin de pâtes');
        $page->setPageType('landing')->save();
        $document = $this->exportedWithType($page, pageType: '../secret');

        $report = $this->getService(SiteImporter::class)->import(SiteDocument::fromArray($document), new ImportOptions(replace: true));

        self::assertSame(PageTypeCode::DEFAULT, $this->reloaded($page)->getPageType());
        self::assertCount(1, $report->warnings());
    }

    /**
     * Starting a page needs the right to write pages, not the right to change
     * the settings: a template cannot bring back a type the site dropped.
     */
    public function testAPageStartedFromATemplateWhoseTypeIsGoneGetsTheDefaultType(): void
    {
        $page = $this->createPage('Modèle de recette');
        $document = $this->exportedWithType($page, pageType: 'type-dropped-since');

        $created = $this->getService(SiteImporter::class)->importPageFrom(SiteDocument::fromArray($document), 0, 'Nouvelle recette', $this->locale());

        self::assertSame(PageTypeCode::DEFAULT, $created->getPageType());
        self::assertFalse($this->types()->exists('type-dropped-since'));
    }

    public function testAnImportedTypeThatIsNotAStringIsTheDefaultType(): void
    {
        $page = $this->createPage('Gratin de pâtes');
        $page->setPageType('landing')->save();
        $document = $this->getService(SiteExporter::class)->exportPage($page);
        $document['pages'][0]['page_type'] = ['recipe'];

        $report = $this->getService(SiteImporter::class)->import(SiteDocument::fromArray($document), new ImportOptions(replace: true));

        self::assertSame(PageTypeCode::DEFAULT, $this->reloaded($page)->getPageType());
        self::assertCount(1, $report->warnings());
    }

    public function testAPageStartedFromATemplateSavedBeforePageTypesGetsItsLayout(): void
    {
        $page = $this->createPage('Modèle de recette');
        $document = $this->exportedWithType($page, legacyLayout: 'full-width');

        $created = $this->getService(SiteImporter::class)->importPageFrom(SiteDocument::fromArray($document), 0, 'Nouvelle recette', $this->locale());

        self::assertSame('full-width', $created->getPageType());
    }

    /**
     * The export of a single page with its type replaced, or carried under the
     * key files written before 1.2.0 used.
     *
     * @return array<string, mixed>
     */
    private function exportedWithType(CmsPage $page, ?string $pageType = null, ?string $legacyLayout = null): array
    {
        $document = $this->getService(SiteExporter::class)->exportPage($page);

        if (null !== $legacyLayout) {
            unset($document['pages'][0]['page_type']);
            $document['pages'][0]['layout'] = $legacyLayout;
        } else {
            $document['pages'][0]['page_type'] = $pageType;
        }

        return $document;
    }

    private function published(CmsPage $page): PublishedPage
    {
        $published = $this->getService(PublishedPageRepository::class)->find((int) $page->getId(), $this->locale());

        self::assertNotNull($published);

        return $published;
    }

    private function reloaded(CmsPage $page): CmsPage
    {
        CmsPageTableMap::clearInstancePool();

        $reloaded = CmsPageQuery::create()->findPk($page->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }

    /**
     * The writer of the container, but with a shared cache that remembers what
     * it was told to drop.
     */
    private function writerPurgingWith(CachePurgerInterface $purger): CmsPageWriter
    {
        $arguments = [];

        foreach ((new \ReflectionMethod(CmsPageWriter::class, '__construct'))->getParameters() as $parameter) {
            /** @var class-string $type */
            $type = (string) $parameter->getType();
            $arguments[] = CachePurger::class === $type
                ? new CachePurger([$purger], new NullLogger())
                : $this->getService($type);
        }

        return new CmsPageWriter(...$arguments);
    }

    private function types(): PageTypeRepository
    {
        return $this->getService(PageTypeRepository::class);
    }

    private function typeWriter(): PageTypeWriter
    {
        return $this->getService(PageTypeWriter::class);
    }
}
