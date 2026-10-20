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
 * A grid of pictures from the media library, each with a caption.
 */
final readonly class GallerySection extends AbstractTemplateSection
{
    public const string NAME = 'Cms:Gallery';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return $this->trans('Gallery');
    }

    public function description(): string
    {
        return $this->trans('A grid of three to twelve pictures from the media library, each with a short caption. Use it to show places, products in use, a team.');
    }

    public function schema(): PropSchema
    {
        return new PropSchema([
            $this->headingProp($this->trans('Heading')),
            Prop::items('items', $this->trans('Pictures'), [
                Prop::image('image', $this->trans('Picture'), help: $this->trans('An image of the media library. Without one, the place is kept for it.')),
                Prop::text('alt', $this->trans('Alternative text'), max: 200, help: $this->trans('What the picture shows, for someone who cannot see it. Empty for a purely decorative picture.')),
                Prop::text('caption', $this->trans('Caption'), max: 120),
            ], min: 1, max: 12),
        ]);
    }

    public function sample(): array
    {
        return [
            'title' => $this->trans('Gallery'),
            'items' => [
                ['image' => null, 'alt' => $this->trans('A first picture'), 'caption' => $this->trans('A caption under it')],
                ['image' => null, 'alt' => $this->trans('A second picture'), 'caption' => ''],
                ['image' => null, 'alt' => $this->trans('A third picture'), 'caption' => $this->trans('Another caption')],
            ],
        ];
    }

    protected function slug(): string
    {
        return 'gallery';
    }
}
