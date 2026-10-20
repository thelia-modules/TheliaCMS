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
 * A picture beside a title, a text and a link.
 */
final readonly class MediaTextSection extends AbstractTemplateSection
{
    public const string NAME = 'Cms:MediaText';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return $this->trans('Text and image');
    }

    public function description(): string
    {
        return $this->trans('A title and a text with a picture beside them, and an optional link. Use it to present one service, one product or one story at a time.');
    }

    public function schema(): PropSchema
    {
        return new PropSchema([
            $this->headingProp($this->trans('Title')),
            Prop::html('text', $this->trans('Text'), help: $this->trans('Paragraphs, lists and emphasis.')),
            Prop::image('image', $this->trans('Picture')),
            Prop::text('image_alt', $this->trans('Alternative text'), max: 200, help: $this->trans('What the picture shows, for someone who cannot see it. Empty for a purely decorative picture.')),
            Prop::text('link_label', $this->trans('Link label'), max: 60),
            Prop::url('link_url', $this->trans('Link address'), help: $this->trans('Where the link leads. Leave the label and the address empty for no link.')),
        ]);
    }

    public function sample(): array
    {
        return [
            'title' => $this->trans('A title for this section'),
            'text' => '<p>'.$this->trans('Describe one thing here, in a few sentences. Add a second block for the next one.').'</p>',
            'image' => null,
            'image_alt' => $this->trans('A picture that illustrates the text'),
            'link_label' => $this->trans('Read more'),
            'link_url' => '/',
        ];
    }

    protected function slug(): string
    {
        return 'media-text';
    }
}
