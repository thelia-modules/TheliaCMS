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
 * The model declined the request (`stop_reason: refusal`). The category, when
 * the API names one, says what kind of content it declined to write.
 */
final class GenerationRefusedException extends GenerationException
{
    public ?string $category = null;

    public static function withCategory(?string $category, ?string $explanation): self
    {
        $exception = self::because(trim('The model declined to write this page. '.($explanation ?? '')));
        $exception->category = $category;

        return $exception;
    }
}
