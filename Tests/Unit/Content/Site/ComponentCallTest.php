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
use TheliaCMS\Content\Prop;
use TheliaCMS\Content\PropSchema;
use TheliaCMS\Content\PropType;
use TheliaCMS\Content\SectionDefinitionInterface;
use TheliaCMS\Content\Site\SiteComponent;
use TheliaCMS\Content\Site\SiteComponentCall;
use TheliaCMS\Content\Site\SiteComponentSection;
use TheliaCMS\Content\Site\SitePropReader;
use TheliaCMS\Content\Site\SiteTemplateRenderer;
use TheliaCMS\Tests\Support\InMemoryDrawableSections;
use TheliaCMS\Tests\Support\LineLogger;
use Twig\Markup;

/**
 * `cms_component()`: a component of the site draws another kept for the
 * site, one level deep (ADR 15).
 */
final class ComponentCallTest extends TestCase
{
    public function testItDrawsTheKeptComponentWithTheValuesTheCallerStoresInsideItsOwnClass(): void
    {
        $stored = ['text' => '<p>Hello</p>', 'heading' => '<b>Photos</b>', 'photos' => [['image' => 'https://example.com/a.jpg', 'alt' => 'A'], ['image' => 'https://example.com/b.jpg', 'alt' => 'B']]];
        $html = $this->renderer()->render(
            '<div class="row"><div>{{ props.text }}</div>{{ cms_component("Carousel", {slides: props.photos, title: props.heading}) }}</div>',
            ['text' => new Markup('<p>Hello</p>', 'UTF-8'), 'heading' => 'ignored: the template form', 'photos' => []],
            $stored,
        );

        self::assertSame('<div class="row"><div><p>Hello</p></div><div class="cms-site cms-site--carousel"><section><h2>&lt;b&gt;Photos&lt;/b&gt;</h2><img src="https://example.com/a.jpg" alt="A" /><img src="https://example.com/b.jpg" alt="B" /></section></div></div>', $html);
    }

    public function testAValueWrittenInTheCallIsPassedAsItIs(): void
    {
        self::assertSame('<div class="cms-site cms-site--echo"><p>yes</p></div>', $this->renderer()->render('{{ cms_component("Echo", {said: "yes"}) }}', []));
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

    public function testANameTheCatalogueDoesNotOfferOrValuesItsSchemaRefusesDrawNothingAndAreLogged(): void
    {
        $logger = $this->logger();

        $html = $this->renderer($logger)->render('<p>{{ cms_component("Draft") }}{{ cms_component("Missing") }}{{ cms_component("Strict", {count: "many"}) }}</p>', []);

        self::assertSame('<p></p>', $html);
        self::assertCount(3, $logger->lines);
        self::assertSame('TheliaCMS: a component of the site draws Draft, which the catalogue does not offer.', $logger->lines[0]);
        self::assertSame('TheliaCMS: a component of the site draws Missing, which the catalogue does not offer.', $logger->lines[1]);
        self::assertStringStartsWith('TheliaCMS: the component Site:Strict cannot be drawn inside another', $logger->lines[2]);
    }

    public function testAComponentOfTheThemeIsPutInPlaceOnceTheMarkupOfTheCallerIsFiltered(): void
    {
        $theme = new class implements SectionDefinitionInterface {
            /** @var list<array<string, mixed>> */
            public array $drawn = [];

            public function name(): string
            {
                return 'Layouts:Products:Base';
            }

            public function label(): string
            {
                return 'Products';
            }

            public function description(): string
            {
                return 'Products of the shop.';
            }

            public function schema(): PropSchema
            {
                return new PropSchema([new Prop('title', PropType::Text, 'Title'), new Prop('picture', PropType::Image, 'Picture')]);
            }

            public function sample(): array
            {
                return [];
            }

            public function normalizeProps(array $props): array
            {
                return $props;
            }

            public function render(array $props, string $locale): string
            {
                $this->drawn[] = $props + ['locale' => $locale];

                return '<div data-controller="products" class="products">'.htmlspecialchars((string) $props['title']).'</div>';
            }
        };
        $parent = new SiteComponent('Row', 'Row', 'A heading and the products.', [['name' => 'heading', 'type' => 'text', 'label' => 'Heading'], ['name' => 'photo', 'type' => 'image', 'label' => 'Photo']], [], '<div class="row">{{ cms_component("Layouts:Products:Base", {title: props.heading, picture: props.photo}) }}</div>');
        $sections = new InMemoryDrawableSections([], [$theme]);
        $renderer = new SiteTemplateRenderer(null, $sections);
        $section = new SiteComponentSection($parent, (new SitePropReader())->read($parent->props), $renderer, sanitizer: new PublishedContentSanitizer());

        $html = $section->render(['heading' => 'Our products', 'photo' => 'https://example.com/p.jpg'], 'fr_FR');

        self::assertSame('<div class="cms-site cms-site--row"><div class="row"><div data-controller="products" class="products">Our products</div></div></div>', $html, 'The attribute the filter of the caller takes out is drawn by the theme.');
        self::assertSame([['title' => 'Our products', 'picture' => 'https://example.com/p.jpg', 'locale' => 'fr_FR']], $theme->drawn, 'The theme is given the values the page stores, in the language of the page.');
    }

    public function testARenderingDrawsAtMostTheCeilingOfCallsAndTheNextRenderingStartsAgain(): void
    {
        $renderer = $this->renderer();
        $template = str_repeat('{{ cms_component("Echo", {said: "x"}) }}', SiteTemplateRenderer::MAX_CALLS + 5);

        $first = $renderer->render($template, []);
        $second = $renderer->render($template, []);

        self::assertSame(SiteTemplateRenderer::MAX_CALLS, substr_count($first, '<p>x</p>'));
        self::assertSame(SiteTemplateRenderer::MAX_CALLS, substr_count($second, '<p>x</p>'), 'The next rendering counts from zero again.');
    }

    public function testWithoutTheSectionsOfTheCatalogueTheFunctionDrawsNothing(): void
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
        yield 'the field of a row' => ['{% for item in props.rows %}{{ cms_component("Echo", {said: item.title}) }}{% endfor %}', 'passes "said" something else than a prop'];
        yield 'a computed value' => ['{{ cms_component("Echo", {said: props.title|upper}) }}', 'passes "said" something else than a prop'];
        yield 'a prop of a prop' => ['{{ cms_component("Echo", {said: props.title.first}) }}', 'passes "said" something else than a prop'];
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
            {{ cms_component('Carousel', {slides: props.photos, title: 'Ours', count: 3}) }}
            {% endfor %}
            {{ cms_component("Layouts:Products:Base") }}
            TWIG);

        self::assertEquals([
            new SiteComponentCall('Carousel', ['slides', 'title', 'count'], 4, ['slides' => 'photos']),
            new SiteComponentCall('Layouts:Products:Base', [], 6),
        ], $calls);
        self::assertSame(['Site:Carousel', 'Layouts:Products:Base'], array_map(static fn (SiteComponentCall $call): string => $call->name(), $calls));
    }

    private function renderer(?AbstractLogger $logger = null): SiteTemplateRenderer
    {
        $sections = new InMemoryDrawableSections([
            new SiteComponent('Carousel', 'Carousel', 'Photos in a row.', [
                ['name' => 'title', 'type' => 'text', 'label' => 'Title'],
                ['name' => 'slides', 'type' => 'items', 'label' => 'Slides', 'items' => [['name' => 'image', 'type' => 'image', 'label' => 'Image'], ['name' => 'alt', 'type' => 'text', 'label' => 'Text']]],
            ], [], '<section><h2>{{ props.title }}</h2>{% for slide in props.slides %}<img src="{{ slide.image }}" alt="{{ slide.alt }}">{% endfor %}</section>'),
            new SiteComponent('Echo', 'Echo', 'Says a word.', [['name' => 'said', 'type' => 'text', 'label' => 'Said']], [], '<p>{{ props.said }}</p>'),
            new SiteComponent('Keys', 'Keys', 'Names what it is given.', [['name' => 'said', 'type' => 'text', 'label' => 'Said']], [], '<p>{{ props|keys|join(",") }}</p>'),
            new SiteComponent('Strict', 'Strict', 'Takes a number.', [['name' => 'count', 'type' => 'integer', 'label' => 'Count']], [], '<p>{{ props.count }}</p>'),
            new SiteComponent('Calling', 'Calling', 'Calls another.', [['name' => 'said', 'type' => 'text', 'label' => 'Said']], [], '<p>{{ props.said }}</p>{{ cms_component("Echo", {said: "deeper"}) }}'),
        ]);
        $renderer = new SiteTemplateRenderer(null, $sections, $logger);
        $sections->renderer = $renderer;

        return $renderer;
    }

    private function logger(): LineLogger
    {
        return new LineLogger();
    }
}
