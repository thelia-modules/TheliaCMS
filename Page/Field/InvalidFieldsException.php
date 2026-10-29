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

namespace TheliaCMS\Page\Field;

/**
 * A definition of fields, or values for them, the site cannot store. Every
 * problem is listed, each one a sentence an editor or the generator can act
 * on.
 */
final class InvalidFieldsException extends \InvalidArgumentException
{
    /**
     * @param list<string> $problems
     */
    private function __construct(
        public readonly array $problems,
    ) {
        parent::__construct(implode(' ', $problems));
    }

    /**
     * @param list<string> $problems
     */
    public static function because(array $problems): self
    {
        return new self($problems);
    }
}
