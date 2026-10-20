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
 * Key figures: a few numbers with what they measure.
 */
final readonly class FiguresSection extends AbstractTemplateSection
{
    public const string NAME = 'Cms:Figures';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return $this->trans('Key figures');
    }

    public function description(): string
    {
        return $this->trans('Two to six striking numbers, each with a short label. Use it to prove experience or results at a glance.');
    }

    public function schema(): PropSchema
    {
        return new PropSchema([
            $this->headingProp($this->trans('Heading')),
            Prop::items('items', $this->trans('Figures'), [
                Prop::text('value', $this->trans('Value'), required: true, max: 20, help: $this->trans('The figure as it is displayed, for instance "98 %".')),
                Prop::text('label', $this->trans('Label'), required: true, max: 80),
            ], min: 1, max: 6),
        ]);
    }

    public function sample(): array
    {
        return [
            'title' => $this->trans('In figures'),
            'items' => [
                ['value' => '12', 'label' => $this->trans('years in business')],
                ['value' => '480', 'label' => $this->trans('projects delivered')],
                ['value' => $this->trans('98 %'), 'label' => $this->trans('customers who come back')],
            ],
        ];
    }

    protected function slug(): string
    {
        return 'figures';
    }
}
