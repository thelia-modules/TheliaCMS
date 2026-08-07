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
 * The outcome of checking what a visitor sent: the answers that got through,
 * and what is wrong with the rest.
 */
final readonly class ValidatedSubmission
{
    /**
     * @param list<Answer>          $answers
     * @param array<string, string> $errors  one message per field name, translated
     * @param array<string, mixed>  $entered what the visitor typed, to fill the form back in
     */
    public function __construct(
        public array $answers,
        public array $errors,
        public array $entered,
    ) {
    }

    public function isValid(): bool
    {
        return [] === $this->errors;
    }

    /**
     * First email address of the submission, which is how a person asks to see
     * or to erase what a site holds about them.
     */
    public function email(): ?string
    {
        foreach ($this->answers as $answer) {
            if (FieldType::Email === $answer->type && \is_string($answer->value) && '' !== $answer->value) {
                return $answer->value;
            }
        }

        return null;
    }

    /**
     * Whether every agreement the form asked for was given.
     *
     * A form with no consent field at all counts as granted: there was nothing
     * to agree to.
     */
    public function consentGranted(): bool
    {
        foreach ($this->answers as $answer) {
            if (FieldType::Consent === $answer->type && true !== $answer->value) {
                return false;
            }
        }

        return true;
    }
}
