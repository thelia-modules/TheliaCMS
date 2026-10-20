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
 * Customer testimonials: a short quote and who said it.
 */
final readonly class TestimonialsSection extends AbstractTemplateSection
{
    public const string NAME = 'Cms:Testimonials';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return $this->trans('Testimonials');
    }

    public function description(): string
    {
        return $this->trans('Two to six customer quotes, each with the name and company of the person. Use it to build trust on a landing or an offer page.');
    }

    public function schema(): PropSchema
    {
        return new PropSchema([
            $this->headingProp($this->trans('Heading')),
            Prop::items('items', $this->trans('Testimonials'), [
                Prop::text('quote', $this->trans('Quote'), required: true, max: 500),
                Prop::text('author', $this->trans('Author'), max: 120, help: $this->trans('Name and company.')),
            ], min: 1, max: 6),
        ]);
    }

    public function sample(): array
    {
        return [
            'title' => $this->trans('What our customers say'),
            'items' => [
                ['quote' => $this->trans('They delivered on time and the result is better than what we had imagined.'), 'author' => $this->trans('Claire Martin, Atelier Lumière')],
                ['quote' => $this->trans('Our orders went up by a third in the first quarter after the redesign.'), 'author' => $this->trans('Paul Girard, Maison Girard')],
                ['quote' => $this->trans('A team that listens, explains, and answers fast.'), 'author' => $this->trans('Sophie Durand, Durand & Fils')],
            ],
        ];
    }

    protected function slug(): string
    {
        return 'testimonials';
    }
}
