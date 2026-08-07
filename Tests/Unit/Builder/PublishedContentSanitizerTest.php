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

namespace TheliaCMS\Tests\Unit\Builder;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheliaCMS\Builder\PublishedContentSanitizer;

/**
 * The editor profile is a client, not a trusted administrator: whatever the
 * browser sends is treated as hostile and filtered on the server before it can
 * reach a published page.
 */
final class PublishedContentSanitizerTest extends TestCase
{
    private PublishedContentSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new PublishedContentSanitizer();
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function hostileHtml(): iterable
    {
        yield 'script element' => ['<p>Hello</p><script>alert(1)</script>', 'alert(1)'];
        yield 'inline event handler' => ['<p onclick="alert(1)">Hello</p>', 'onclick'];
        yield 'error handler on a broken image' => ['<img src="x" onerror="alert(1)">', 'onerror'];
        yield 'javascript scheme in a link' => ['<a href="javascript:alert(1)">x</a>', 'javascript:'];
        yield 'javascript scheme with mixed case' => ['<a href="JaVaScRiPt:alert(1)">x</a>', 'avascript'];
        yield 'data url document in a link' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', 'text/html'];
        yield 'form posting elsewhere' => ['<form action="https://evil.test"><input name="p"></form>', 'evil.test'];
        yield 'inline svg' => ['<svg><script>alert(1)</script></svg>', '<svg'];
        yield 'object element' => ['<object data="https://evil.test/x.swf"></object>', '<object'];
        yield 'embed element' => ['<embed src="https://evil.test/x">', '<embed'];
        yield 'base tag rewriting every link' => ['<base href="https://evil.test/">', '<base'];
        yield 'meta refresh' => ['<meta http-equiv="refresh" content="0;url=https://evil.test">', 'evil.test'];
        yield 'style element' => ['<style>body{background:url(https://evil.test/x)}</style>', 'evil.test'];
        yield 'srcdoc iframe' => ['<iframe srcdoc="<script>alert(1)</script>"></iframe>', 'srcdoc'];
    }

    #[DataProvider('hostileHtml')]
    public function testStripsHostileMarkup(string $html, string $forbidden): void
    {
        self::assertStringNotContainsStringIgnoringCase(
            $forbidden,
            (string) $this->sanitizer->html($html),
        );
    }

    public function testKeepsTheMarkupAPageIsMadeOf(): void
    {
        $html = '<div class="cms-page-content"><h1 id="title">Hello</h1>'
            .'<p style="color:#333">A <strong>bold</strong> <em>claim</em>, with a '
            .'<a href="/contact" title="Contact">link</a>.</p>'
            .'<ul><li>One</li></ul>'
            .'<img src="/image-library/1/full/max/0/default.jpg" alt="A picture" '
            .'width="800" height="600" loading="lazy" decoding="async" fetchpriority="high">'
            .'</div>';

        $sanitized = (string) $this->sanitizer->html($html);

        foreach ([
            'class="cms-page-content"', 'id="title"', '<h1', '<strong>', '<em>',
            'href="/contact"', 'title="Contact"', '<li>One</li>', 'alt="A picture"',
            'width="800"', 'height="600"', 'loading="lazy"', 'decoding="async"',
            'fetchpriority="high"', 'style="color:#333"',
        ] as $expected) {
            self::assertStringContainsString($expected, $sanitized);
        }
    }

    /**
     * The catalogue names each section by its own heading and hides decorative
     * icons; dropping those attributes on the way out would undo, silently, the
     * one thing the blocks are careful about.
     */
    public function testKeepsWhatMakesAPageAccessible(): void
    {
        $html = '<section aria-labelledby="cms-hero-title" role="region">'
            .'<h2 id="cms-hero-title">Hello</h2>'
            .'<i class="icon" aria-hidden="true"></i>'
            .'<a href="/" aria-current="page">Home</a>'
            .'<details open><summary>A question</summary><p>An answer</p></details>'
            .'<p lang="en" dir="ltr">In English</p>'
            .'</section>';

        $sanitized = (string) $this->sanitizer->html($html);

        foreach ([
            'aria-labelledby="cms-hero-title"', 'role="region"', 'aria-hidden="true"',
            'aria-current="page"', '<details open', 'lang="en"', 'dir="ltr"',
        ] as $expected) {
            self::assertStringContainsString($expected, $sanitized);
        }
    }

    public function testKeepsThePartialMarkersTheRendererSubstitutes(): void
    {
        $html = '<div data-cms-partial="cms-form" data-props=\'{"code":"contact"}\'></div>';

        $sanitized = (string) $this->sanitizer->html($html);

        self::assertStringContainsString('data-cms-partial="cms-form"', $sanitized);
        self::assertStringContainsString('data-props', $sanitized);
    }

    public function testDropsAnIframeUnlessCustomCodeIsAllowed(): void
    {
        $html = '<iframe src="https://player.test/embed/1" title="A video"></iframe>';

        self::assertStringNotContainsString('<iframe', (string) $this->sanitizer->html($html));
        self::assertStringContainsString('<iframe', (string) $this->sanitizer->html($html, allowCustomCode: true));
    }

    public function testLeavesEmptyContentAlone(): void
    {
        self::assertNull($this->sanitizer->html(null));
        self::assertSame('', $this->sanitizer->html(''));
        self::assertSame('   ', $this->sanitizer->html('   '));
        self::assertNull($this->sanitizer->css(null));
        self::assertSame('', $this->sanitizer->css(''));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function hostileCss(): iterable
    {
        yield 'import of a remote sheet' => ['@import url("https://evil.test/x.css"); p{color:red}', '@import'];
        yield 'import without url()' => ["@import 'https://evil.test/x.css';", '@import'];
        yield 'legacy expression' => ['p{width:expression(alert(1))}', 'expression('];
        yield 'behavior property' => ['p{behavior:url(#default#time2)}', 'behavior'];
        yield 'remote background' => ['p{background:url(https://evil.test/track.png)}', 'evil.test'];
        yield 'protocol relative background' => ['p{background:url(//evil.test/track.png)}', 'evil.test'];
    }

    #[DataProvider('hostileCss')]
    public function testFiltersHostileCss(string $css, string $forbidden): void
    {
        self::assertStringNotContainsStringIgnoringCase($forbidden, (string) $this->sanitizer->css($css));
    }

    public function testKeepsTheCssAPageIsStyledWith(): void
    {
        $css = '.cms-page-content h1{color:#333;font-size:2rem}'
            .'.cms-page-content .hero{background-image:url(/image-library/1/full/max/0/default.jpg)}'
            .'.cms-page-content .logo{background-image:url(data:image/png;base64,iVBORw0KGgo=)}'
            .'@media (min-width:768px){.cms-page-content h1{font-size:3rem}}';

        self::assertSame($css, $this->sanitizer->css($css));
    }
}
