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

namespace TheliaCMS\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * Keeps each line logged, its placeholders filled from the context.
 */
final class LineLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $lines = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->lines[] = strtr((string) $message, array_map(static fn (mixed $value): string => \is_scalar($value) ? (string) $value : '', array_combine(array_map(static fn (string $key): string => '{'.$key.'}', array_keys($context)), $context)));
    }
}
