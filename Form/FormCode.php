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
 * The code of a form: what a page refers to it by, and what the page prints in
 * the script that reports a sent form. Checked wherever a code comes from
 * outside the form screen, an import file first of all.
 */
final class FormCode
{
    public const int MAX_LENGTH = 50;

    /** `D`: without it, `$` also matches before a trailing newline. */
    public const string PATTERN = '/^[a-z0-9-]+$/D';

    public static function isValid(string $code): bool
    {
        return \strlen($code) <= self::MAX_LENGTH && 1 === preg_match(self::PATTERN, $code);
    }
}
