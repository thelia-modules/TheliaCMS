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

namespace TheliaCMS\Content;

/**
 * A document that cannot be stored: wrong shape, unknown component, props a
 * component refuses. Every problem is listed, so whoever produced the document
 * — an editor, an import, a model — is told everything at once.
 */
final class InvalidDocumentException extends \InvalidArgumentException
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
        return new self([] === $problems ? ['The document is invalid.'] : $problems);
    }
}
