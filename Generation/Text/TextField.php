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

namespace TheliaCMS\Generation\Text;

/**
 * The rich-text field a text is written for: what it is for decides how long
 * it runs and what it says.
 */
enum TextField: string
{
    case Summary = 'summary';
    case Description = 'description';
    case Conclusion = 'conclusion';

    /**
     * What the model is told about the field.
     */
    public function brief(): string
    {
        return match ($this) {
            self::Summary => 'The summary: one or two short paragraphs shown above the description and in lists, that make the reader want to read on. No heading.',
            self::Description => 'The detailed description: the main text, a few paragraphs, with subheadings and lists when the content calls for them.',
            self::Conclusion => 'The conclusion: one short paragraph shown after the description, that closes the text or points to what to do next. No heading.',
        };
    }
}
