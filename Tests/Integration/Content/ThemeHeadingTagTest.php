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

use TheliaCMS\Content\Document;
use TheliaCMS\Content\Section;
use TheliaCMS\Content\SectionCatalog;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContent;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Page\Admin\CmsPageWriter;
use TheliaCMS\Page\Admin\EmptyPageContentException;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * The heading band of Flexy prints its `tag` prop as the name of an element
 * (`<{{ tag }} class="Subheader-title">`), and the module reads it as a free
 * text the editor and the page generator fill. Whatever is written there, the
 * page keeps no script: the draft and the published page are filtered.
 */
final class ThemeHeadingTagTest extends CmsIntegrationTestCase
{
    private const string BAND = 'Layouts:Subheader:Base';

    private const string SCRIPT = '/<(script|iframe)\b|\son[a-z]+\s*=/i';

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function hostileTags(): iterable
    {
        yield 'an image with a handler' => ['img src=x onerror=alert(1)', true];
        yield 'a frame' => ['iframe src=https://example.com', true];
        // The whole band is a script: the filter takes it out, and the page
        // has nothing left to publish.
        yield 'a script' => ['script', false];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('hostileTags')]
    public function testATagWrittenInTheHeadingBandLeavesNoScriptInThePage(string $tag, bool $drawn): void
    {
        if (null === $this->getService(SectionCatalog::class)->find(self::BAND)) {
            self::markTestSkipped('The theme opens no heading band to pages.');
        }

        $page = $this->createPage('Bandeau', published: false);
        $writer = $this->getService(CmsPageWriter::class);

        $writer->saveContent($page, $this->locale(), new Document([new Section('band', self::BAND, ['title' => 'alert(2)', 'tag' => $tag])]));
        $draft = (string) $this->content($page)->getDraftHtml();

        self::assertDoesNotMatchRegularExpression(self::SCRIPT, $draft, 'The draft holds no script.');

        if ($drawn) {
            self::assertStringContainsString('alert(2)', $draft, 'The band is drawn, its tag filtered.');
        } else {
            self::assertStringNotContainsString('alert(2)', $draft, 'Nothing of the band is left.');
            $this->expectException(EmptyPageContentException::class);
        }

        $writer->publish($page, $this->locale());

        self::assertDoesNotMatchRegularExpression(self::SCRIPT, (string) $this->content($page)->getPublishedHtml(), 'The published page holds no script.');
    }

    private function content(CmsPage $page): CmsPageContent
    {
        $content = CmsPageContentQuery::create()->filterByPageId($page->getId())->filterByLocale($this->locale())->findOne();
        self::assertNotNull($content);

        return $content;
    }
}
