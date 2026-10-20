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
 * A titled section on a background of its own, to group free text.
 */
final readonly class SectionSection extends AbstractTemplateSection
{
    public const string NAME = 'Cms:Section';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return $this->trans('Section');
    }

    public function description(): string
    {
        return $this->trans('A titled section on a background of its own, to group free text. Use it for a passage that needs a heading and some room: a presentation, a policy, a story.');
    }

    public function schema(): PropSchema
    {
        return new PropSchema([
            $this->headingProp($this->trans('Title')),
            Prop::html('text', $this->trans('Content'), required: true, help: $this->trans('The content of the section. Headings start at h3.')),
        ]);
    }

    public function sample(): array
    {
        return [
            'title' => $this->trans('Section title'),
            'text' => '<p>'.$this->trans('Drop other blocks in here to group them on a background of their own.').'</p>',
        ];
    }

    protected function slug(): string
    {
        return 'section';
    }
}
