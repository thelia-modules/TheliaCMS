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

use PHPUnit\Framework\TestCase;
use TheliaCMS\Content\PropType;
use TheliaCMS\Content\Site\InvalidSiteComponentException;
use TheliaCMS\Content\Site\SiteComponent;
use TheliaCMS\Content\Site\SiteComponentSection;
use TheliaCMS\Content\Site\SiteComponentValidator;
use TheliaCMS\Content\Site\SitePropReader;
use TheliaCMS\Content\Site\SiteTemplateRenderer;

/**
 * Whether a component written by the model can be kept, and as what.
 */
final class SiteComponentValidatorTest extends TestCase
{
    public function testAComponentThatHoldsIsGivenBackWithItsSampleNormalisedAndItsStylesFiltered(): void
    {
        $kept = (new SiteComponentValidator())->validate(PriceTable::component(css: '.plan { background: url(https://tracker.example/p.png); }'));

        self::assertSame('Site:PriceTable', $kept->name());
        self::assertSame('cms-site--price-table', $kept->scopeClass());
        self::assertSame('light', $kept->sample['tone'], 'The sample gets the defaults it left out.');
        self::assertStringNotContainsString('tracker.example', $kept->css, 'A stylesheet reaches no other host.');
    }

    public function testEveryProblemIsListedAtOnce(): void
    {
        $component = new SiteComponent('price-table', '', 'A table.', [['name' => 'title', 'type' => 'reference', 'label' => 'Title']], [], '<div onclick="steal()">{{ props.title|raw }}</div>', '@import url(x.css);');

        try {
            (new SiteComponentValidator())->validate($component);
            self::fail('A component that does not hold is refused.');
        } catch (InvalidSiteComponentException $exception) {
            $problems = implode("\n", $exception->problems);

            self::assertStringContainsString('PascalCase', $problems);
            self::assertStringContainsString('label', $problems);
            self::assertStringContainsString('"reference"', $problems);
            self::assertStringContainsString('"raw"', $problems);
            self::assertStringContainsString('onclick=', $problems);
            self::assertStringContainsString('@import', $problems);
        }
    }

    public function testAStylesheetThatCouldCloseTheElementHoldingItIsRefused(): void
    {
        $this->expectException(InvalidSiteComponentException::class);
        $this->expectExceptionMessage('"<"');

        (new SiteComponentValidator())->validate(PriceTable::component(css: '.plan::after { content: "</style><script>alert(1)</script>"; }'));
    }

    public function testATemplateThatWritesItsOwnTextIsRefused(): void
    {
        $this->expectException(InvalidSiteComponentException::class);
        $this->expectExceptionMessage('"Choose your plan"');

        (new SiteComponentValidator())->validate(PriceTable::component('<section><h2>Choose your plan</h2>{% for plan in props.plans %}<p>{{ plan.name }} – {{ plan.price }}</p>{% endfor %}</section>'));
    }

    public function testASampleThePropsRefuseIsNamedAsSuch(): void
    {
        $this->expectException(InvalidSiteComponentException::class);
        $this->expectExceptionMessage('The sample:');

        (new SiteComponentValidator())->validate(PriceTable::component(sample: ['plans' => [['price' => '9 €']]]));
    }

    public function testASampleThatDrawsNothingIsRefused(): void
    {
        $this->expectException(InvalidSiteComponentException::class);
        $this->expectExceptionMessage('draws nothing');

        (new SiteComponentValidator())->validate(PriceTable::component('{% if props.title == "never" %}<p>{{ props.title }}</p>{% endif %}'));
    }

    public function testTheSectionDrawsTheComponentInsideItsClassAndAFailingTemplateDrawsNothing(): void
    {
        $component = PriceTable::component();
        $schema = (new SitePropReader())->read($component->props);
        $section = new SiteComponentSection($component, $schema, new SiteTemplateRenderer());
        $html = $section->render($section->normalizeProps($component->sample), 'fr_FR');

        self::assertStringStartsWith('<div class="cms-site cms-site--price-table"><section class="price-table price-table--light"><h2>Our plans</h2>', $html);
        self::assertStringContainsString('<article class="plan plan--featured"><h3>Pro</h3><p class="plan__price">29 €</p><p>All studios</p></article>', $html, 'Markup props are printed as they are.');
        self::assertSame(PropType::Items, $section->schema()->props[1]->type);

        $broken = new SiteComponentSection(PriceTable::component('{% include "x" %}'), $schema, new SiteTemplateRenderer());
        self::assertSame('', $broken->render($component->sample, 'fr_FR'), 'A page keeps rendering whatever one of its components does.');
    }
}
