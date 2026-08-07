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
 * One field of a form, resolved for the language it is being shown in.
 *
 * A plain value object: everything the template renders and everything the
 * validator checks is here, so both work without touching the database — which
 * is what makes the whole of the validation testable on its own.
 */
final readonly class Field
{
    /**
     * @param list<string> $choices
     */
    public function __construct(
        public string $name,
        public FieldType $type,
        public string $label,
        public bool $required = false,
        public string $placeholder = '',
        public string $help = '',
        public array $choices = [],
        public int $rows = 4,
    ) {
    }

    /**
     * Identifier of the input in the rendered page.
     *
     * Prefixed, because a page may hold several forms and a bare `email` would
     * be claimed by the first of them — with a `<label for>` pointing at the
     * wrong input.
     */
    public function inputId(string $formCode): string
    {
        return \sprintf('cms-form-%s-%s', $formCode, $this->name);
    }
}
