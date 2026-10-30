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

namespace TheliaCMS\Menu\Admin;

/**
 * Whether a failure of a write comes from a unique index, however many
 * exceptions (Propel wraps the one of PDO) it travelled through.
 */
final class UniqueViolation
{
    private const string INTEGRITY_CONSTRAINT = '23000';

    public static function caused(\Throwable $throwable): bool
    {
        for ($current = $throwable; null !== $current; $current = $current->getPrevious()) {
            if ($current instanceof \PDOException && self::INTEGRITY_CONSTRAINT === (string) ($current->errorInfo[0] ?? $current->getCode())) {
                return true;
            }

            if (str_contains($current->getMessage(), 'Duplicate entry') || str_contains($current->getMessage(), 'SQLSTATE[23000]')) {
                return true;
            }
        }

        return false;
    }
}
