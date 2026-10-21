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

namespace TheliaCMS\Tests\Unit\Content\Site;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheliaCMS\Content\Site\SiteTemplateRenderer;
use Twig\Markup;
use Twig\Sandbox\SecurityError;

/**
 * The Twig of the components of the site: sandboxed, given the props alone.
 */
final class SiteTemplateRendererTest extends TestCase
{
    public function testTextIsEscapedAndMarkupPrintedAsItIs(): void
    {
        $html = (new SiteTemplateRenderer())->render(
            '{% for item in props.items %}<p class="{{ loop.last ? "last" }}">{{ item.title|upper }} {{ item.body }}</p>{% endfor %}{% for i in 1..2 %}{{ i }}{% endfor %}',
            ['items' => [['title' => '<b>a</b>', 'body' => new Markup('<em>b</em>', 'UTF-8')], ['title' => 'c', 'body' => '<i>d</i>']]],
        );

        self::assertSame('<p class="">&lt;B&gt;A&lt;/B&gt; <em>b</em></p><p class="last">C &lt;i&gt;d&lt;/i&gt;</p>12', $html);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'an include' => ['{% include "base.html.twig" %}'];
        yield 'the raw filter' => ['{{ props.title|raw }}'];
        yield 'a function of the site' => ['{{ path("admin") }}'];
        yield 'a macro' => ['{% macro m() %}{% endmacro %}'];
        yield 'a block' => ['{% block b %}{% endblock %}'];
        yield 'a filter running code' => ['{{ props.items|map(i => i) }}'];
        yield 'the constant test' => ['{% if props.a is constant("PHP_VERSION") %}x{% endif %}'];
        yield 'a syntax error' => ['{% if %}'];
    }

    #[DataProvider('refused')]
    public function testWhatTheSandboxRefusesIsAProblemBeforeAnyRendering(string $template): void
    {
        self::assertNotSame([], (new SiteTemplateRenderer())->problems($template));
    }

    public function testARefusedTemplateIsNeverRendered(): void
    {
        $this->expectException(SecurityError::class);

        (new SiteTemplateRenderer())->render('{% include "base.html.twig" %}', []);
    }
}
