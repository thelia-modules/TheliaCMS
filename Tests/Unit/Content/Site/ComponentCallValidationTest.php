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
use TheliaCMS\Content\Site\InvalidSiteComponentException;
use TheliaCMS\Content\Site\SiteComponent;
use TheliaCMS\Content\Site\SiteComponentValidator;
use TheliaCMS\Content\Site\SitePropReader;
use TheliaCMS\Content\Site\SiteTemplateRenderer;
use TheliaCMS\Tests\Support\InMemoryDrawableSections;
use TheliaCMS\Tests\Support\InMemorySiteComponents;

/**
 * What the validator says of the components a template draws with
 * `cms_component()` (ADR 15).
 */
final class ComponentCallValidationTest extends TestCase
{
    private const array CAROUSEL_PROPS = [
        ['name' => 'title', 'type' => 'text', 'label' => 'Title'],
        ['name' => 'slides', 'type' => 'items', 'label' => 'Slides', 'min' => 1, 'items' => [
            ['name' => 'image', 'type' => 'image', 'label' => 'Image', 'required' => true],
            ['name' => 'alt', 'type' => 'text', 'label' => 'Text'],
        ]],
    ];

    public function testAComponentThatDrawsAKeptOneWithItsPropsIsKeptAndItsSampleDrawsBoth(): void
    {
        $kept = $this->validator()->validate($this->row('{{ cms_component("Carousel", {slides: props.photos, title: props.heading}) }}'));

        self::assertSame('Row', $kept->code);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedCalls(): iterable
    {
        yield 'itself' => ['{{ cms_component("Row", {heading: props.heading}) }}', 'Line 1: the component draws itself.'];
        yield 'a prototype' => ['{{ cms_component("Draft") }}', 'Line 1: the catalogue offers no component Draft'];
        yield 'a code the site does not hold' => ['{{ cms_component("Slider") }}', 'Line 1: the catalogue offers no component Slider'];
        yield 'a component that draws others' => ['{{ cms_component("Calling") }}', 'Line 1: Calling draws other components itself'];
        yield 'a key the component does not declare' => ['{{ cms_component("Carousel", {pictures: props.photos}) }}', 'Line 1: Carousel has no prop "pictures"; its props are title, slides.'];
        yield 'a prop of another type' => ['{{ cms_component("Carousel", {title: props.photos}) }}', 'Line 1: props.photos is of type items, and the prop "title" of Carousel of type text.'];
        yield 'rows without a required field' => ['{{ cms_component("Carousel", {slides: props.links}) }}', 'Line 1: the rows of props.links have no "image", which the rows of "slides" of Carousel need'];
        yield 'the field of a row' => ['{% for link in props.links %}{{ cms_component("Echo", {said: link.alt}) }}{% endfor %}', 'Line 1: cms_component passes "said" something else than a prop'];
        yield 'rows with a field of another type' => ['{{ cms_component("Carousel", {slides: props.wrong}) }}', 'Line 1: "image" of the rows of props.wrong is of type text, and Carousel reads it as of type image.'];
    }

    #[DataProvider('refusedCalls')]
    public function testACallTheComponentCannotHoldIsRefused(string $call, string $said): void
    {
        try {
            $this->validator()->validate($this->row($call));
            self::fail('The call is refused.');
        } catch (InvalidSiteComponentException $exception) {
            self::assertStringContainsString($said, implode("\n", $exception->problems));
        }
    }

    public function testAComponentAnotherDrawsCannotStartDrawingOthers(): void
    {
        $carousel = new SiteComponent('Carousel', 'Carousel', 'Photos in a row.', self::CAROUSEL_PROPS, ['slides' => [['image' => 1]]], '<section>{% for slide in props.slides %}<img src="{{ slide.image }}" alt="{{ slide.alt }}">{% endfor %}</section>{{ cms_component("Echo", {said: props.title}) }}');

        try {
            $this->validator()->validate($carousel);
            self::fail('The call is refused.');
        } catch (InvalidSiteComponentException $exception) {
            self::assertContains('The component Carousel is drawn by Gallery: a component draws others one level deep, so one that another draws cannot draw any itself.', $exception->problems);
        }
    }

    public function testATextAndAMarkupPropReadAsEachOther(): void
    {
        $kept = $this->validator()->validate($this->row('{{ cms_component("Echo", {said: props.body}) }}'));

        self::assertSame('Row', $kept->code);
    }

    private function row(string $call): SiteComponent
    {
        return new SiteComponent('Row', 'Row', 'A heading next to a carousel.', [
            ['name' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ['name' => 'body', 'type' => 'html', 'label' => 'Body'],
            ['name' => 'photos', 'type' => 'items', 'label' => 'Photos', 'items' => [['name' => 'image', 'type' => 'image', 'label' => 'Image'], ['name' => 'alt', 'type' => 'text', 'label' => 'Text']]],
            ['name' => 'links', 'type' => 'items', 'label' => 'Links', 'items' => [['name' => 'alt', 'type' => 'text', 'label' => 'Text']]],
            ['name' => 'wrong', 'type' => 'items', 'label' => 'Wrong', 'items' => [['name' => 'image', 'type' => 'text', 'label' => 'Image']]],
        ], ['heading' => 'Photos', 'body' => '<p>Hello</p>', 'photos' => [['image' => 'https://example.com/a.jpg', 'alt' => 'A']]], '<div class="row"><h2>{{ props.heading }}</h2>'.$call.'</div>');
    }

    private function validator(): SiteComponentValidator
    {
        $components = new InMemorySiteComponents([
            new SiteComponent('Carousel', 'Carousel', 'Photos in a row.', self::CAROUSEL_PROPS, [], '<section>{% for slide in props.slides %}<img src="{{ slide.image }}" alt="{{ slide.alt }}">{% endfor %}</section>'),
            new SiteComponent('Echo', 'Echo', 'Says a word.', [['name' => 'said', 'type' => 'html', 'label' => 'Said']], [], '<p>{{ props.said }}</p>'),
            new SiteComponent('Calling', 'Calling', 'Calls another.', [['name' => 'said', 'type' => 'text', 'label' => 'Said']], [], '<p>{{ props.said }}</p>{{ cms_component("Echo", {said: "x"}) }}'),
            new SiteComponent('Gallery', 'Gallery', 'Calls the carousel.', [['name' => 'said', 'type' => 'text', 'label' => 'Said']], [], '<div>{{ cms_component("Carousel", {title: props.said}) }}</div>'),
        ], [
            new SiteComponent('Draft', 'Draft', 'A prototype.', [['name' => 'said', 'type' => 'text', 'label' => 'Said']], [], '<p>draft</p>'),
        ]);

        $sections = new InMemoryDrawableSections(array_values(array_filter($components->all(), static fn (SiteComponent $component): bool => null !== $components->kept($component->code))));
        $renderer = new SiteTemplateRenderer(null, $sections);
        $sections->renderer = $renderer;

        return new SiteComponentValidator(new SitePropReader(), $renderer, components: $components, sections: $sections);
    }
}
