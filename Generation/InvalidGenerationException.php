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
 * What the model answered is not a document the site can store, even after
 * it was told what was wrong and asked again.
 *
 * @property list<string> $problems
 */
final class InvalidGenerationException extends GenerationException
{
    /** @var list<string> */
    public array $problems = [];

    /**
     * @param list<string> $problems
     */
    public static function withProblems(array $problems): self
    {
        $exception = self::because('The model answered with a document the site cannot store: '.implode(' ', $problems));
        $exception->problems = $problems;

        return $exception;
    }
}
