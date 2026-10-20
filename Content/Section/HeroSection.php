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
 * The opening of a page: one sentence that says what the page is about, a
 * line under it, and the one thing the visitor is invited to do.
 */
final readonly class HeroSection extends AbstractTemplateSection
{
    public const string NAME = 'Cms:Hero';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return $this->trans('Hero');
    }

    public function description(): string
    {
        return $this->trans('The opening of a page: its main heading, one or two lines under it, and one call to action. Use it once, first.');
    }

    public function schema(): PropSchema
    {
        return new PropSchema([
            Prop::text('title', $this->trans('Heading'), required: true, max: 120, help: $this->trans('The sentence that says what the page is about. It is the main heading of the page.')),
            Prop::choice('heading_level', $this->trans('Heading level'), ['1' => 'h1', '2' => 'h2'], '1', $this->trans('1 when the hero opens the page, 2 when the page already has a main heading.')),
            Prop::text('text', $this->trans('Text'), max: 400, help: $this->trans('One or two lines that say it in more detail.')),
            Prop::text('cta_label', $this->trans('Button label'), max: 60),
            Prop::url('cta_url', $this->trans('Button address'), help: $this->trans('Where the button leads. Leave the label and the address empty for no button.')),
        ]);
    }

    public function sample(): array
    {
        return [
            'title' => $this->trans('The sentence that says what you do'),
            'heading_level' => '1',
            'text' => $this->trans('One or two lines to say it in more detail, in the words your customers use.'),
            'cta_label' => $this->trans('Get in touch'),
            'cta_url' => '/contact',
        ];
    }

    protected function slug(): string
    {
        return 'hero';
    }
}
