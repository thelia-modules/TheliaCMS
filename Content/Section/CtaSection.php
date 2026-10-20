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
 * A band that invites the visitor to do one thing, with a button.
 */
final readonly class CtaSection extends AbstractTemplateSection
{
    public const string NAME = 'Cms:Cta';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return $this->trans('Call to action');
    }

    public function description(): string
    {
        return $this->trans('A heading, one line and a button that invites the visitor to act: contact, buy, subscribe. Use it to close a page or a long section.');
    }

    public function schema(): PropSchema
    {
        return new PropSchema([
            $this->headingProp($this->trans('Heading')),
            Prop::text('text', $this->trans('Text'), max: 300, help: $this->trans('One line that says what happens when the visitor clicks.')),
            Prop::text('button_label', $this->trans('Button label'), required: true, max: 60),
            Prop::url('button_url', $this->trans('Button address'), required: true, help: $this->trans('Where the button leads.')),
        ]);
    }

    public function sample(): array
    {
        return [
            'title' => $this->trans('Ready to start?'),
            'text' => $this->trans('One line that says what happens when they click.'),
            'button_label' => $this->trans('Contact us'),
            'button_url' => '/contact',
        ];
    }

    protected function slug(): string
    {
        return 'cta';
    }
}
