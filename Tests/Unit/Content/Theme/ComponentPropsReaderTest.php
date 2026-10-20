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

namespace TheliaCMS\Tests\Unit\Content\Theme;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TheliaCMS\Content\Prop;
use TheliaCMS\Content\PropType;
use TheliaCMS\Content\Theme\ComponentPropsReader;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * What a component of the theme may ask for, read off its template: the
 * records of the site by their id, pictures of the library, and lists of
 * records, next to the scalars.
 */
final class ComponentPropsReaderTest extends TestCase
{
    public function testAPropMayNameARecordOfTheSiteOrAPictureOfTheLibrary(): void
    {
        $read = $this->reader()->readSource(<<<'TWIG'
            {# @description A card of one product. #}
            {# @prop productId product The product shown #}
            {# @prop poster image|null A picture of the library over it #}
            {# @prop categoryId category|null Where the link goes #}
            {% props productId, poster = null, categoryId = null, title = '' %}
            TWIG);
        $props = $this->byName($read);

        self::assertSame('A card of one product.', $read['description']);
        self::assertSame(PropType::Reference, $props['product_id']->type);
        self::assertTrue($props['product_id']->required);
        self::assertSame('/admin.cms.partials.sources.products', $props['product_id']->picker);
        self::assertTrue($props['product_id']->searchable, 'Products are searched, not listed.');
        self::assertSame(PropType::Image, $props['poster']->type);
        self::assertFalse($props['poster']->required);
        self::assertSame(PropType::Reference, $props['category_id']->type);
        self::assertFalse($props['category_id']->required);
        self::assertSame(PropType::Text, $props['title']->type);
        self::assertSame([], $read['id_lists']);
    }

    public function testAListOfRecordsIsRowsOfIdsForTheDocumentAndIsToldApart(): void
    {
        $read = $this->reader()->readSource(<<<'TWIG'
            {# @prop productIds product[] The products of the row, in order #}
            {# @prop folders list<folder> #}
            {# @prop tags array Free tags #}
            {% props productIds = [], folders = [], tags = [] %}
            TWIG);
        $props = $this->byName($read);

        self::assertSame(PropType::Items, $props['product_ids']->type);
        self::assertSame(12, $props['product_ids']->maxItems);
        self::assertSame('id', $props['product_ids']->items[0]->name);
        self::assertSame(PropType::Reference, $props['product_ids']->items[0]->type);
        self::assertSame('/admin.cms.partials.sources.products', $props['product_ids']->items[0]->picker);
        self::assertSame(PropType::Items, $props['folders']->type);
        self::assertSame(PropType::Json, $props['tags']->type, 'A plain array stays a JSON prop.');
        self::assertSame(['product_ids', 'folders'], $read['id_lists']);
    }

    public function testAPropDocumentedButNotDeclaredIsOneTheClassTakesAndIsOptional(): void
    {
        $read = $this->reader()->readSource(<<<'TWIG'
            {# @prop title string The heading #}
            {# @prop productIds product[] Picked by hand #}
            {# @prop itemsPerPage int How many #}
            {% props title = '' %}
            TWIG);
        $props = $this->byName($read);

        self::assertSame(['title', 'product_ids', 'items_per_page'], array_keys($props), 'The declared prop first, then what the class takes.');
        self::assertFalse($props['items_per_page']->required);
        self::assertSame(PropType::Integer, $props['items_per_page']->type);
        self::assertSame(['product_ids'], $read['id_lists']);
    }

    /**
     * @return array<string, Prop>
     */
    private function byName(array $read): array
    {
        $props = [];

        foreach ($read['schema']->props as $prop) {
            $props[$prop->name] = $prop;
        }

        return $props;
    }

    private function reader(): ComponentPropsReader
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route): string => '/'.$route);

        return new ComponentPropsReader(new Environment(new ArrayLoader()), $urls);
    }
}
