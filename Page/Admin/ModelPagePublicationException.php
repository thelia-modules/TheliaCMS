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

namespace TheliaCMS\Page\Admin;

/**
 * The model of a page type is the demo its pages are copied from: online, it
 * would be a page of made-up content in every list of the type.
 */
final class ModelPagePublicationException extends \RuntimeException
{
    public static function for(int $pageId): self
    {
        return new self(\sprintf('CMS page #%d is the model of its type and is never published.', $pageId));
    }
}
