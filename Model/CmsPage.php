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

namespace TheliaCMS\Model;

use Thelia\Model\Tools\UrlRewritingTrait;
use TheliaCMS\Model\Base\CmsPage as BaseCmsPage;
use TheliaCMS\Storage\EncodesSupplementaryCharacters;
use TheliaCMS\TheliaCMS;

class CmsPage extends BaseCmsPage
{
    // Spells out the characters the connection of the site cannot carry, an
    // emoji in a title among them.
    use EncodesSupplementaryCharacters;
    // Brings setRewrittenUrl(), which also flags the previous URL as redirected
    // so renaming a page keeps a 301 behind it.
    use UrlRewritingTrait;

    public function getRewrittenUrlViewName(): string
    {
        return TheliaCMS::PAGE_VIEW;
    }
}
