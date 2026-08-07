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
 * Publishing was asked of a page whose draft shows nothing.
 *
 * Raised by the writer rather than checked by each screen: a page put online
 * with no content is served as a 404 while every back-office screen calls it
 * published, and that has to be impossible whichever way publishing is asked
 * for.
 */
final class EmptyPageContentException extends \RuntimeException
{
    public static function for(int $pageId, string $locale): self
    {
        return new self(\sprintf('CMS page #%d has no content to publish in %s.', $pageId, $locale));
    }
}
