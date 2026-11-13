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
use Psr\Log\AbstractLogger;
use TheliaCMS\Content\Pipeline\PublishedContentSanitizer;
use TheliaCMS\Content\Site\SiteComponent;
use TheliaCMS\Content\Site\SiteComponentCall;
use TheliaCMS\Content\Site\SiteComponentSection;
use TheliaCMS\Content\Site\SitePropReader;
use TheliaCMS\Content\Site\SiteTemplateRenderer;
use TheliaCMS\Tests\Support\InMemorySiteComponents;
use Twig\Markup;

/**
 * `cms_component()`: a component of the site draws another kept for the
 * site, one level deep (ADR 15).
 */
final class ComponentCallTest extends TestCase
{
    public function testItDrawsTheKeptComponentWithTheValuesGivenInsideItsOwnClass(): void
    {
        $html = $this->renderer()->render(
            '<div class="row"><div>{{ props.text }}</div>{{ cms_component("Carousel", {slides: props.photos, title: props.heading}) }}</div>',
            ['text' => new Markup('<p>Hello</p>', 'UTF-8'), 'heading' => '<b>Photos</b>', 'photos' => [['image' => '/a.jpg', 'alt' => 'A'], ['image' => '/b.jpg', 'alt' => 'B']]],
        );

        self::assertSame('<div class="row"><div><p>Hello</p></div><div class="cms-site cms-site--carousel"><section><h2>&lt;b&gt;Photos&lt;/b&gt;</h2><img src="/a.jpg" alt="A"><img src="/b.jpg" alt="B"></section></div></div>', $html);
    }

    public function testTheKeysTheComponentDoesNotDeclareAreLeftOut(): void
    {
        $html = $this->renderer()->render('{{ cms_component("Keys", {said: "yes", other: "no"}) }}', []);

        self::assertSame('<div class="cms-site cms-site--keys"><p>said</p></div>', $html);
    }

    public function testAComponentCalledByAnotherDrawsNoComponentItself(): void
    {
        $html = $this->renderer()->render('{{ cms_component("Calling", {said: "inner"}) }}', []);

        self::assertSame('<div class="cms-site cms-site--calling"><p>inner</p></div>', $html);
    }

    public function testAPrototypeOrAnUnknownCodeDrawsNothingAndIsLogged(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $lines = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->lines[] = strtr((string) $message, ['{code}' => (string) ($context['code'] ?? '')]);
            }
        };

        $html = $this->renderer($logger)->render('<p>{{ cms_component("Draft") }}{{ cms_component("Missing") }}</p>', []);

        self::assertSame('<p></p>', $html);
        self::assertSame([
            'TheliaCMS: a component of the site draws Draft, which the site does not keep.',
            'TheliaCMS: a component of the site draws Missing, which the site does not keep.',
        ], $logger->lines);
    }

    public function testARenderingDrawsAtMostTheCeilingOfCallsAndTheNextRenderingStartsAgain(): void
    {
        $renderer = $this->renderer();
        $rows = array_fill(0, SiteTemplateRenderer::MAX_CALLS + 5, ['said' => 'x']);
        $template = '{% for row in props.rows %}{{ cms_component("Echo", {said: row.said}) }}{% endfor %}';

        $first = $renderer->render($template, ['rows' => $rows]);
        $second = $renderer->render($template, ['rows' => $rows]);

        self::assertSame(SiteTemplateRenderer::MAX_CALLS, substr_count($first, '<p>x</p>'));
        self::assertSame(SiteTemplateRenderer::MAX_CALLS, substr_count($second, '<p>x</p>'), 'The next rendering counts from zero again.');
    }

    public function testWithoutTheComponentsOfTheSiteTheFunctionDrawsNothing(): void
    {
        self::assertSame('<p></p>', (new SiteTemplateRenderer())->render('<p>{{ cms_component("Echo", {said: "x"}) }}</p>', []));
    }

    public function testTheSectionKeepsTheElementOfTheComponentDrawnInsideThroughTheFilterOfPages(): void
    {
        $parent = new SiteComponent('Row', 'Row', 'A text and a carousel.', [['name' => 'heading', 'type' => 'text', 'label' => 'Heading']], ['heading' => 'Photos'], '<div class="row">{{ cms_component("Echo", {said: props.heading}) }}</div>');
        $section = new SiteComponentSection($parent, (new SitePropReader())->read($parent->props), $this->renderer(), sanitizer: new PublishedContentSanitizer());

        self::assertSame('<div class="cms-site cms-site--row"><div class="row"><div class="cms-site cms-site--echo"><p>Photos</p></div></div></div>', $section->render(['heading' => 'Photos'], 'en_US'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedCalls(): iterable
    {
        yield 'a code held by a prop' => ['{{ cms_component(props.code) }}', 'the first value of cms_component is the code'];
        yield 'a code that is not one' => ['{{ cms_component("carousel") }}', 'the first value of cms_component is the code'];
        yield 'values held by a prop' => ['{{ cms_component("Carousel", props) }}', 'written as a mapping'];
        yield 'a key computed' => ['{{ cms_component("Carousel", {(props.key): 1}) }}', 'written as a mapping'];
        yield 'a third value' => ['{{ cms_component("Carousel", {}, 1) }}', 'and nothing else'];
        yield 'named values' => ['{{ cms_component(code: "Carousel") }}', 'and nothing else'];
        yield 'a call inside the values of another' => ['{{ cms_component("Carousel", {title: cms_component("Echo")}) }}', 'never another cms_component'];
    }

    #[DataProvider('refusedCalls')]
    public function testACallWrittenInAWayTheSandboxCannotFollowIsRefused(string $template, string $said): void
    {
        $problems = $this->renderer()->problems($template);

        self::assertCount(1, $problems);
        self::assertStringContainsString($said, $problems[0]);
        self::assertSame([], $this->renderer()->calls($template));
    }

    public function testTheCallsAreReadInTheTreeWithThePropsPassedAsTheyAre(): void
    {
        $calls = $this->renderer()->calls(<<<'TWIG'
            {# cms_component("Commented") #}
            <p>{{ "cms_component('Text')" }}</p>
            {% for item in props.rows %}
            {{ cms_component('Carousel', {slides: props.photos, title: item.title, count: 3}) }}
            {% endfor %}
            {{ cms_component("Echo") }}
            TWIG);

        self::assertEquals([
            new SiteComponentCall('Carousel', ['slides', 'title', 'count'], 4, ['slides' => 'photos']),
            new SiteComponentCall('Echo', [], 6),
        ], $calls);
    }

    private function renderer(?AbstractLogger $logger = null): SiteTemplateRenderer
    {
        return new SiteTemplateRenderer(null, new InMemorySiteComponents([
            new SiteComponent('Carousel', 'Carousel', 'Photos in a row.', [
                ['name' => 'title', 'type' => 'text', 'label' => 'Title'],
                ['name' => 'slides', 'type' => 'items', 'label' => 'Slides', 'items' => [['name' => 'image', 'type' => 'image', 'label' => 'Image'], ['name' => 'alt', 'type' => 'text', 'label' => 'Text']]],
            ], [], '<section><h2>{{ props.title }}</h2>{% for slide in props.slides %}<img src="{{ slide.image }}" alt="{{ slide.alt }}">{% endfor %}</section>'),
            new SiteComponent('Echo', 'Echo', 'Says a word.', [['name' => 'said', 'type' => 'text', 'label' => 'Said']], [], '<p>{{ props.said }}</p>'),
            new SiteComponent('Keys', 'Keys', 'Names what it is given.', [['name' => 'said', 'type' => 'text', 'label' => 'Said']], [], '<p>{{ props|keys|join(",") }}</p>'),
            new SiteComponent('Calling', 'Calling', 'Calls another.', [['name' => 'said', 'type' => 'text', 'label' => 'Said']], [], '<p>{{ props.said }}</p>{{ cms_component("Echo", {said: "deeper"}) }}'),
        ], [
            new SiteComponent('Draft', 'Draft', 'A prototype.', [['name' => 'said', 'type' => 'text', 'label' => 'Said']], [], '<p>draft</p>'),
        ]), $logger);
    }
}
