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

namespace TheliaCMS\Form;

/**
 * What one field was answered with, together with the question as it was asked.
 *
 * The label travels with the answer rather than being looked up later: a form
 * gets reworded, fields get removed, and a submission read a year afterwards
 * still has to say what the person was actually answering.
 */
final readonly class Answer
{
    public function __construct(
        public string $name,
        public FieldType $type,
        public string $label,
        public string|bool $value,
    ) {
    }

    /**
     * The answer as a line of text — what an export column and an email body
     * hold.
     */
    public function asText(string $yes = 'Yes', string $no = 'No'): string
    {
        if (\is_bool($this->value)) {
            return $this->value ? $yes : $no;
        }

        return $this->value;
    }
}
