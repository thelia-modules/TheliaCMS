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
 * The model of a page type is a reference its pages start from: it is never
 * online, and it goes with its type rather than to the bin, where it would be
 * restored as a page nobody can reach from the tree.
 */
final class ModelPageSettingException extends \RuntimeException
{
    public static function trash(int $pageId): self
    {
        return new self(\sprintf('CMS page #%d is the model of its type: it is deleted with its type, never moved to the bin.', $pageId));
    }

    public static function visibility(int $pageId): self
    {
        return new self(\sprintf('CMS page #%d is the model of its type: it is never online.', $pageId));
    }
}
