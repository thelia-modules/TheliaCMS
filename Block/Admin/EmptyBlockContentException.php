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

namespace TheliaCMS\Block\Admin;

/**
 * Publishing was asked of a reusable block whose draft shows nothing.
 *
 * Raised by the writer, like the one on pages: a block put online empty does
 * not break its own address, it leaves a gap in every page showing it, and
 * those pages are not the one being looked at when it happens.
 */
final class EmptyBlockContentException extends \RuntimeException
{
    public static function for(int $blockId, string $locale): self
    {
        return new self(\sprintf('CMS block #%d has no content to publish in %s.', $blockId, $locale));
    }
}
