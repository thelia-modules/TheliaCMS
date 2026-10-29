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

namespace TheliaCMS\Generation;

/**
 * The generator could not hand back a document. The subclasses say why, and
 * every one of them is logged before it is thrown.
 */
class GenerationException extends \RuntimeException
{
    /** The row of the ledger that recorded the failure, when there is one. */
    public ?int $logId = null;

    final private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function because(string $message, ?\Throwable $previous = null): static
    {
        return new static($message, $previous);
    }
}
