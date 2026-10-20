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

final class UnknownSectionException extends \RuntimeException
{
    public static function for(Section $section): self
    {
        return new self(\sprintf('Section "%s" uses "%s", which is not a component of the catalogue.', $section->id, $section->component));
    }
}
