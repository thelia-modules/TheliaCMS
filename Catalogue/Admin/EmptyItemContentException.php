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

namespace TheliaCMS\Catalogue\Admin;

use TheliaCMS\Catalogue\CatalogueItemType;

/**
 * Publishing was asked of the document of an item whose draft shows nothing.
 */
final class EmptyItemContentException extends \RuntimeException
{
    public static function for(CatalogueItemType $type, int $id, string $locale): self
    {
        return new self(\sprintf('The %s #%d has no CMS content to publish in %s.', $type->value, $id, $locale));
    }
}
