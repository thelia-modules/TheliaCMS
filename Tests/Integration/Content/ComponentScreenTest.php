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

namespace TheliaCMS\Tests\Integration\Content;

use Symfony\Component\DomCrawler\Crawler;
use Thelia\Core\Security\AccessManager;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Content\Document;
use TheliaCMS\Content\DocumentRenderer;
use TheliaCMS\Content\DocumentSchema;
use TheliaCMS\Content\RichTextSection;
use TheliaCMS\Content\Section;
use TheliaCMS\Content\SectionCatalog;
use TheliaCMS\Generation\Admin\ToolkitLink;
use TheliaCMS\Generation\Brief;
use TheliaCMS\Generation\CatalogSheet;
use TheliaCMS\Generation\GenerationRequest;
use TheliaCMS\Generation\GenerationTarget;
use TheliaCMS\Generation\Tool\ReadSectionTool;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * CMS > Components, requested over HTTP by an administrator: the list of the
 * catalogue with the pages using each component, putting one away and back,
 * and what putting it away changes for the generator and for the pages.
 */
final class ComponentScreenTest extends AdminScreenTestCase
{
    private const string SCREEN = '/admin/cms/components';

    private const string ACTION = '/admin/cms/components/availability';

    /** A component the module ships, so the test does not depend on the theme. */
    private const string COMPONENT = 'Partial:cms-form';

    public function testTheScreenListsTheCatalogueWithThePagesUsingEachComponent(): void
    {
        $before = (int) $this->cell($this->screen(self::SCREEN), 'pages', RichTextSection::NAME)->attr('data-pages');

        $this->createPage('Page counted by the components');
        $screen = $this->screen(self::SCREEN);

        self::assertSame($before + 1, (int) $this->cell($screen, 'pages', RichTextSection::NAME)->attr('data-pages'), 'The new page is counted.');
        self::assertSame('1', $this->cell($screen, 'offered', self::COMPONENT)->attr('data-offered'));
        self::assertStringContainsString('Partial', $this->cell($screen, 'origin', self::COMPONENT)->text());
        self::assertSame(0, $this->cell($screen, 'toggle', RichTextSection::NAME)->count(), 'Free text cannot be put away.');

        $entry = $screen->filter('[data-testid="bo-nav-cms-components"]');
        self::assertSame(1, $entry->count(), 'The sidebar offers the components.');
        self::assertSame(self::SCREEN, parse_url((string) $entry->attr('href'), \PHP_URL_PATH));
    }

    public function testTheScreenLinksToTheToolkitWhereItAnswers(): void
    {
        $toolkit = $this->getService(ToolkitLink::class);
        \assert($toolkit instanceof ToolkitLink);
        $screen = $this->screen(self::SCREEN);
        $link = $screen->filter('[data-testid="cms-components-toolkit"]');
        $see = $this->cell($screen, 'see', self::COMPONENT);

        if (null === $toolkit->url()) {
            self::assertSame(0, $link->count(), 'No link where the toolkit does not answer.');
            self::assertSame(0, $see->count());

            return;
        }

        self::assertSame(1, $link->count(), 'The screen links to the toolkit.');
        self::assertSame($toolkit->url(), $link->attr('href'));
        self::assertSame($toolkit->url().'#partial-cms-form', $see->attr('href'), 'Each component links to its section of the toolkit page.');
    }

    public function testADisabledComponentIsNoLongerOfferedToTheGeneratorAndComesBack(): void
    {
        self::assertSame(302, $this->toggle(self::COMPONENT, false));

        $list = $this->client->followRedirect();
        self::assertSame('0', $this->cell($list, 'offered', self::COMPONENT)->attr('data-offered'));
        self::assertStringContainsString('no longer offered', $list->filter('[role="alert"]')->text(''));

        $catalog = $this->catalog();
        self::assertNotNull($catalog->find(self::COMPONENT), 'The component is still known: the pages using it render.');
        self::assertNotContains(self::COMPONENT, $catalog->offeredNames());
        self::assertNotContains(self::COMPONENT, $this->componentsOfTheStrictSchema([]));
        self::assertStringNotContainsString('`'.self::COMPONENT.'`', $this->sheet()->text());

        self::assertSame(302, $this->toggle(self::COMPONENT, true));

        self::assertSame('1', $this->cell($this->client->followRedirect(), 'offered', self::COMPONENT)->attr('data-offered'));
        self::assertContains(self::COMPONENT, $this->catalog()->offeredNames());
        self::assertStringContainsString('`'.self::COMPONENT.'`', $this->sheet()->text());
    }

    public function testARevisionMayKeepADisabledComponentTheDocumentAlreadyUses(): void
    {
        self::assertSame(302, $this->toggle(self::COMPONENT, false));

        $target = GenerationTarget::page($this->createPage('Page revised with a disabled component'), $this->locale());
        $document = new Document([new Section('contact', self::COMPONENT, ['form' => 1])]);
        $request = GenerationRequest::revision($target, $this->locale(), $document, new Brief('Shorter.'));

        self::assertContains(self::COMPONENT, $this->componentsOfTheStrictSchema($request->keptComponents()));

        $tool = $this->getService(ReadSectionTool::class);
        \assert($tool instanceof ReadSectionTool);
        self::assertStringContainsString('`'.self::COMPONENT.'`', $tool->run(['component' => self::COMPONENT], $request), 'The sheet of a kept component is read.');

        $fresh = GenerationRequest::fromBrief($target, $this->locale(), new Brief('A contact page.'));
        self::assertStringStartsWith('The catalogue has no such component.', $tool->run(['component' => self::COMPONENT], $fresh));

        $renderer = $this->getService(DocumentRenderer::class);
        \assert($renderer instanceof DocumentRenderer);
        self::assertStringContainsString('data-cms-section="contact"', $renderer->render($document, $this->locale()), 'A page using it still renders.');
    }

    public function testFreeTextCannotBePutAway(): void
    {
        self::assertSame(400, $this->toggle(RichTextSection::NAME, false));
        self::assertContains(RichTextSection::NAME, $this->catalog()->offeredNames());
    }

    public function testAComponentTheCatalogueDoesNotHoldAnswers404(): void
    {
        self::assertSame(404, $this->toggle('Cms:Nothing', false));
    }

    public function testSeeingTheComponentsIsNotTheRightToPutThemAway(): void
    {
        $token = $this->tokenOfTheForm($this->screen(self::SCREEN), self::ACTION);

        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([
            CmsResources::SETTINGS => [AccessManager::VIEW],
        ]));

        $screen = $this->screen(self::SCREEN);
        self::assertSame(1, $this->cell($screen, 'offered', self::COMPONENT)->count(), 'The list is readable.');
        self::assertSame(0, $screen->filter('[data-testid^="cms-component-toggle-"]')->count(), 'No button is offered.');

        self::assertSame(403, $this->send('POST', self::ACTION, ['_token' => $token, 'component' => self::COMPONENT, 'offered' => '0']));
        self::assertContains(self::COMPONENT, $this->catalog()->offeredNames());
    }

    private function toggle(string $component, bool $offered): int
    {
        $token = $this->tokenOfTheForm($this->screen(self::SCREEN), self::ACTION);

        return $this->send('POST', self::ACTION, ['_token' => $token, 'component' => $component, 'offered' => $offered ? '1' : '0']);
    }

    private function cell(Crawler $screen, string $column, string $component): Crawler
    {
        return $screen->filter(\sprintf('[data-testid="cms-component-%s-%s"]', $column, $component));
    }

    /**
     * @param list<string> $kept
     *
     * @return list<string>
     */
    private function componentsOfTheStrictSchema(array $kept): array
    {
        $schema = $this->getService(DocumentSchema::class);
        \assert($schema instanceof DocumentSchema);

        $properties = $schema->strict($kept)['properties']['sections']['items']['properties'];

        return $properties['component']['enum'];
    }

    private function catalog(): SectionCatalog
    {
        $catalog = $this->getService(SectionCatalog::class);
        \assert($catalog instanceof SectionCatalog);

        return $catalog;
    }

    private function sheet(): CatalogSheet
    {
        $sheet = $this->getService(CatalogSheet::class);
        \assert($sheet instanceof CatalogSheet);

        return $sheet;
    }
}
