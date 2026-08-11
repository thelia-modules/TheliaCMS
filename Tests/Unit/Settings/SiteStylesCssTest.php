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

namespace TheliaCMS\Tests\Unit\Settings;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Settings\FontStacks;
use TheliaCMS\Settings\SiteStylesCss;
use TheliaCMS\Settings\SiteTypography;

final class SiteStylesCssTest extends TestCase
{
    private SiteStylesCss $css;
    private FontStacks $stacks;

    protected function setUp(): void
    {
        $this->stacks = new FontStacks();
        $this->css = new SiteStylesCss($this->stacks);
    }

    public function testNothingConfiguredWritesNothing(): void
    {
        self::assertSame('', $this->css->build(SiteTypography::fromArray([], $this->stacks), []));
    }

    public function testEveryRuleIsScopedToTheCmsContent(): void
    {
        $typography = SiteTypography::fromArray([
            'h1' => ['size' => '2.5rem'],
            'p' => ['lineHeight' => '1.6'],
            'a' => ['color' => '#1d4ed8', 'hoverColor' => '#111827'],
            'button' => ['background' => '#1d4ed8', 'radius' => '6px'],
        ], $this->stacks);

        $sheet = $this->css->build($typography, []);

        foreach (explode("\n", $sheet) as $line) {
            if (str_ends_with($line, '{')) {
                self::assertStringContainsString('.cms-page-content', $line, $line);
            }
        }

        self::assertStringContainsString(".cms-page-content h1 {\n    font-size: 2.5rem;\n}", $sheet);
        self::assertStringContainsString(".cms-page-content a:hover {\n    color: #111827;\n}", $sheet);
        // The shop outside the CMS content keeps the style of its theme.
        self::assertStringNotContainsString("\nh1", $sheet);
    }

    public function testAButtonRuleCoversWhatAThemeCallsAButton(): void
    {
        $typography = SiteTypography::fromArray(['button' => ['background' => '#1d4ed8']], $this->stacks);

        $sheet = $this->css->build($typography, []);

        self::assertStringContainsString('.cms-page-content button', $sheet);
        self::assertStringContainsString('.cms-page-content .btn', $sheet);
        self::assertStringContainsString('.cms-page-content input[type="submit"]', $sheet);
    }

    public function testAStackIsWrittenAsItsFaces(): void
    {
        $typography = SiteTypography::fromArray(['p' => ['font' => 'stack:transitional']], $this->stacks);

        self::assertStringContainsString(
            'font-family: Charter, "Bitstream Charter", "Sitka Text", Cambria, serif;',
            $this->css->build($typography, []),
        );
    }

    public function testAnUploadedFontIsDeclaredOnceAndReferencedByItsFamily(): void
    {
        $typography = SiteTypography::fromArray([
            'h1' => ['font' => 'file:Recoleta-Bold.woff2'],
            'h2' => ['font' => 'file:Recoleta-Bold.woff2'],
        ], $this->stacks);

        $sheet = $this->css->build($typography, ['Recoleta-Bold.woff2' => '/cms/fonts/Recoleta-Bold.woff2']);

        self::assertSame(1, substr_count($sheet, '@font-face'));
        self::assertStringContainsString('src: url("/cms/fonts/Recoleta-Bold.woff2") format("woff2");', $sheet);
        self::assertStringContainsString('font-display: swap;', $sheet);
        self::assertStringContainsString('font-family: "Recoleta-Bold", system-ui, sans-serif;', $sheet);
    }

    public function testAFontFileTheSiteNoLongerHasIsNotDeclared(): void
    {
        $typography = SiteTypography::fromArray(['h1' => ['font' => 'file:Gone.woff2']], $this->stacks);

        // No URL was given for it: the family still applies, with its
        // fallbacks carrying the text, but no @font-face points at a 404.
        $sheet = $this->css->build($typography, []);

        self::assertStringNotContainsString('@font-face', $sheet);
        self::assertStringContainsString('font-family: "Gone", system-ui, sans-serif;', $sheet);
    }

    public function testTheUnderlineChoiceBecomesATextDecoration(): void
    {
        $typography = SiteTypography::fromArray(['a' => ['underline' => 'none']], $this->stacks);

        self::assertStringContainsString('text-decoration: none;', $this->css->build($typography, []));
    }
}
