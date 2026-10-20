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
 * Questions and answers: each question opens to reveal its answer.
 */
final readonly class QuestionsSection extends AbstractTemplateSection
{
    public const string NAME = 'Cms:Questions';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return $this->trans('Questions');
    }

    public function description(): string
    {
        return $this->trans('A list of questions that open on click to show their answer. Use it for a FAQ, or to settle the doubts that stop a visitor from ordering.');
    }

    public function schema(): PropSchema
    {
        return new PropSchema([
            $this->headingProp($this->trans('Heading')),
            Prop::items('items', $this->trans('Questions'), [
                Prop::text('question', $this->trans('Question'), required: true, max: 200),
                Prop::html('answer', $this->trans('Answer'), required: true, help: $this->trans('The answer, in one or two paragraphs.')),
            ], min: 1, max: 12),
        ]);
    }

    public function sample(): array
    {
        return [
            'title' => $this->trans('Questions and answers'),
            'items' => [
                ['question' => $this->trans('How long does delivery take?'), 'answer' => $this->trans('<p>Most orders leave our workshop within two working days.</p>')],
                ['question' => $this->trans('Can I return an item?'), 'answer' => $this->trans('<p>Yes, within thirty days, as long as it is unused.</p>')],
                ['question' => $this->trans('Do you ship abroad?'), 'answer' => $this->trans('<p>We ship across Europe. Contact us for other destinations.</p>')],
            ],
        ];
    }

    protected function slug(): string
    {
        return 'questions';
    }
}
