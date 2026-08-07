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
 * A form as a visitor sees it, in one language.
 *
 * Everything the template renders and nothing else — the recipients of the
 * submissions are read from the record by the controller that sends the mail,
 * so they cannot end up in a page by accident.
 */
final readonly class FormDefinition
{
    /**
     * @param list<Field> $fields
     */
    public function __construct(
        public int $id,
        public string $code,
        public string $title,
        public string $description,
        public string $submitLabel,
        public string $successMessage,
        public string $legalNotice,
        public ?string $privacyPolicyUrl,
        public array $fields,
    ) {
    }

    public function hasFields(): bool
    {
        return [] !== $this->fields;
    }

    /**
     * Anchor of the form in the page, so the browser comes back to it after a
     * submission rather than to the top of a long page.
     */
    public function anchor(): string
    {
        return 'cms-form-'.$this->code;
    }
}
