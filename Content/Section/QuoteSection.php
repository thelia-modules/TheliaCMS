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
 * A sentence worth repeating, with the name of the person who said it.
 */
final readonly class QuoteSection extends AbstractTemplateSection
{
    public const string NAME = 'Cms:Quote';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return $this->trans('Quote');
    }

    public function description(): string
    {
        return $this->trans('One short quotation, set apart, with its author. Use it to bring a customer, a partner or an expert voice into the page.');
    }

    public function schema(): PropSchema
    {
        return new PropSchema([
            Prop::text('text', $this->trans('Quotation'), required: true, max: 500),
            Prop::text('author', $this->trans('Author'), max: 120, help: $this->trans('Their name and what they do.')),
        ]);
    }

    public function sample(): array
    {
        return [
            'text' => $this->trans('A sentence worth repeating, from someone worth quoting.'),
            'author' => $this->trans('Their name, what they do'),
        ];
    }

    protected function slug(): string
    {
        return 'quote';
    }
}
