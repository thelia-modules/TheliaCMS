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

namespace TheliaCMS\Content\Section;

use TheliaCMS\Content\Prop;
use TheliaCMS\Content\PropSchema;

/**
 * A row of logos of the organisations that trust the site.
 */
final readonly class LogosSection extends AbstractTemplateSection
{
    public const string NAME = 'Cms:Logos';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return $this->trans('Logos');
    }

    public function description(): string
    {
        return $this->trans('Up to twelve logos from the media library, each optionally linking to the organisation. Use it to show clients, partners or labels.');
    }

    public function schema(): PropSchema
    {
        return new PropSchema([
            $this->headingProp($this->trans('Heading')),
            Prop::items('items', $this->trans('Logos'), [
                Prop::image('image', $this->trans('Logo'), help: $this->trans('An image of the media library. Without one, the place is kept for it.')),
                Prop::text('alt', $this->trans('Alternative text'), max: 120, help: $this->trans('The name of the organisation.')),
                Prop::url('url', $this->trans('Link')),
            ], min: 1, max: 12),
        ]);
    }

    public function sample(): array
    {
        return [
            'title' => $this->trans('They trust us'),
            'items' => [
                ['image' => null, 'alt' => $this->trans('First organisation'), 'url' => ''],
                ['image' => null, 'alt' => $this->trans('Second organisation'), 'url' => ''],
                ['image' => null, 'alt' => $this->trans('Third organisation'), 'url' => ''],
                ['image' => null, 'alt' => $this->trans('Fourth organisation'), 'url' => ''],
            ],
        ];
    }

    protected function slug(): string
    {
        return 'logos';
    }
}
