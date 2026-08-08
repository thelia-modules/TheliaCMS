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

use TheliaCMS\Install\LegalPageTemplates;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Page\SampleTextPageFinder;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * Finding the pages that are online and still hold the text the installer put in
 * them.
 *
 * Publishing one of them is refused by CmsPageWriter, so the fixtures here write
 * the published columns themselves — which is exactly the state of a site set up
 * before that refusal existed, and the only state worth warning about.
 */
final class SampleTextPageFinderTest extends CmsIntegrationTestCase
{
    public function testAPublishedPageStillHoldingItsSampleTextIsFound(): void
    {
        $page = $this->publishedWithSampleText('Mentions légales', $this->sampleHtml());

        $found = $this->finder()->publishedPagesStillHoldingSampleText($this->locale());
        $ids = array_column($found['pages'], 'id');
        $position = array_search((int) $page->getId(), $ids, true);

        self::assertNotFalse($position, 'A page online with the installer text in it was not found.');
        self::assertSame(
            'Mentions légales',
            $found['pages'][$position]['title'],
            'The warning names the page, so somebody can go and write it.',
        );
        self::assertGreaterThanOrEqual(1, $found['total']);
    }

    /**
     * The sentence broken up by markup is still the sample text.
     *
     * The database is only asked for one word of it, because that is cheap; the
     * answer is settled by the same check publishing uses, which reads the text a
     * visitor would. An editor who opened the page in the builder and saved it
     * again gets the sentence wrapped in whatever markup the editor produces.
     */
    public function testTheSampleTextIsFoundEvenWhenTagsRunThroughIt(): void
    {
        $sentence = $this->sampleSentence();
        $cut = (int) (mb_strlen($sentence) / 2);

        $page = $this->publishedWithSampleText(
            'Mentions découpées',
            '<h1>Mentions</h1><p>'.mb_substr($sentence, 0, $cut).'<strong>'.mb_substr($sentence, $cut).'</strong></p>',
        );

        self::assertContains(
            (int) $page->getId(),
            array_column($this->finder()->publishedPagesStillHoldingSampleText($this->locale())['pages'], 'id'),
        );
    }

    /**
     * A page somebody actually wrote is not named.
     */
    public function testAPageThatWasWrittenIsNotNamed(): void
    {
        $written = $this->createPage(
            'Mentions légales rédigées',
            html: '<h1>Mentions légales</h1><p>Éditeur du site: OpenStudio, 5 rue Rochon, Clermont-Ferrand.</p>',
        );

        self::assertNotContains(
            (int) $written->getId(),
            array_column($this->finder()->publishedPagesStillHoldingSampleText($this->locale())['pages'], 'id'),
        );
    }

    /**
     * A written page using one of the words the query looks for is not named.
     *
     * The database is asked for a single word from each sample sentence, which is
     * cheap and is a superset: a real cookies page says "lifetime", a real
     * accessibility statement says "conformance". Everything the query hands back
     * is settled by the check publishing uses, and this is the case that proves
     * the settling happens.
     */
    public function testAWrittenPageUsingOneOfTheWordsTheQueryLooksForIsNotNamed(): void
    {
        $written = $this->publishedWithSampleText(
            'Déclaration d’accessibilité rédigée',
            '<h1>Accessibilité</h1><p>The conformance level of this site was audited in June 2026, '
            .'and the lifetime of each cookie is listed on the cookies page.</p>',
        );

        self::assertNotContains(
            (int) $written->getId(),
            array_column($this->finder()->publishedPagesStillHoldingSampleText($this->locale())['pages'], 'id'),
            'A page the query had to look at, and that says nothing of the sample text, was named anyway.',
        );
    }

    /**
     * The sample text is only a problem once it is online. A draft holding it is
     * the state the installer leaves the site in, on purpose.
     */
    public function testADraftHoldingTheSampleTextIsNotNamed(): void
    {
        $draft = $this->createPage('Mentions à écrire', html: $this->sampleHtml(), published: false);

        self::assertNotContains(
            (int) $draft->getId(),
            array_column($this->finder()->publishedPagesStillHoldingSampleText($this->locale())['pages'], 'id'),
        );
    }

    /**
     * A page in the bin is neither reachable nor listed, so naming it would send
     * the reader after something that is not there.
     */
    public function testAPageInTheBinIsNotNamed(): void
    {
        $binned = $this->publishedWithSampleText('Mentions au rebut', $this->sampleHtml());

        self::assertContains(
            (int) $binned->getId(),
            array_column($this->finder()->publishedPagesStillHoldingSampleText($this->locale())['pages'], 'id'),
            'The fixture proves nothing unless the page was found before it was binned.',
        );

        $this->writer()->moveToTrash($binned);

        self::assertNotContains(
            (int) $binned->getId(),
            array_column($this->finder()->publishedPagesStillHoldingSampleText($this->locale())['pages'], 'id'),
        );
    }

    private function finder(): SampleTextPageFinder
    {
        return $this->getService(SampleTextPageFinder::class);
    }

    /**
     * A page online with `$html` behind it, written straight into the published
     * columns because the writer refuses to put this text there.
     */
    private function publishedWithSampleText(string $title, string $html): CmsPage
    {
        $page = $this->createPage($title, html: '<h1>'.htmlspecialchars($title).'</h1><p>Un texte quelconque.</p>');

        $content = CmsPageContentQuery::create()
            ->filterByPageId($page->getId())
            ->filterByLocale($this->locale())
            ->findOne();

        self::assertNotNull($content);

        $content->setPublishedHtml($html)->save();

        return $page;
    }

    private function sampleHtml(): string
    {
        return LegalPageTemplates::html(LegalPageTemplates::PAGES['legal-notice'][$this->templateLocale()]);
    }

    private function sampleSentence(): string
    {
        return LegalPageTemplates::PAGES['legal-notice'][$this->templateLocale()]['intro'];
    }

    private function templateLocale(): string
    {
        return \array_key_exists($this->locale(), LegalPageTemplates::PAGES['legal-notice'])
            ? $this->locale()
            : 'en_US';
    }
}
