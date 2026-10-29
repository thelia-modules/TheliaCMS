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

use TheliaCMS\Content\Site\SiteComponent;

/**
 * A component of the site as the model would write it: a table of plans,
 * every text a prop.
 */
final class PriceTable
{
    public static function component(string $template = '', string $css = '', ?array $sample = null): SiteComponent
    {
        return new SiteComponent(
            'PriceTable',
            'Price table',
            'Two to four plans side by side, each with its price and what it includes.',
            [
                ['name' => 'title', 'type' => 'text', 'label' => 'Heading', 'max' => 120],
                ['name' => 'plans', 'type' => 'items', 'label' => 'Plans', 'min' => 1, 'max' => 4, 'items' => [
                    ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'max' => 60],
                    ['name' => 'price', 'type' => 'text', 'label' => 'Price', 'max' => 30],
                    ['name' => 'details', 'type' => 'html', 'label' => 'What it includes'],
                    ['name' => 'featured', 'type' => 'boolean', 'label' => 'Put forward'],
                ]],
                ['name' => 'tone', 'type' => 'choice', 'label' => 'Tone', 'choices' => ['light' => 'Light', 'dark' => 'Dark'], 'default' => 'light'],
            ],
            $sample ?? ['title' => 'Our plans', 'plans' => [['name' => 'Basic', 'price' => '9 €', 'details' => '<ul><li>One studio</li></ul>', 'featured' => false], ['name' => 'Pro', 'price' => '29 €', 'details' => '<p>All studios</p>', 'featured' => true]]],
            '' !== $template ? $template : self::TEMPLATE,
            $css,
        );
    }

    /**
     * A version the pages written with the first one cannot hold: every plan
     * now needs a `title`, and its `name` is gone.
     */
    public static function renamingThePlanName(): SiteComponent
    {
        $component = self::component('<section>{% for plan in props.plans %}<h3>{{ plan.title }}</h3>{% endfor %}</section>');
        $props = $component->props;
        $props[1]['items'][0] = ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true, 'max' => 60];

        return new SiteComponent($component->code, $component->label, $component->description, $props, ['plans' => [['title' => 'Basic'], ['title' => 'Pro']]], $component->template, $component->css);
    }

    public const string TEMPLATE = '<section class="price-table price-table--{{ props.tone }}">{% if props.title is not empty %}<h2>{{ props.title }}</h2>{% endif %}<div class="price-table__plans">{% for plan in props.plans %}<article class="plan{{ plan.featured ? " plan--featured" }}"><h3>{{ plan.name }}</h3><p class="plan__price">{{ plan.price }}</p>{{ plan.details }}</article>{% endfor %}</div></section>';
}
