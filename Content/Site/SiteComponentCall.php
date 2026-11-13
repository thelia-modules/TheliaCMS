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

namespace TheliaCMS\Content\Site;

/**
 * A `cms_component('<Code>', {key: value})` written in the template of a
 * component of the site, as read in its tree.
 */
final readonly class SiteComponentCall
{
    /**
     * @param list<string>          $keys  the keys of the values it passes
     * @param array<string, string> $props the key passed and the prop of the caller it passes as it is (`slides: props.photos`), when it is one
     */
    public function __construct(
        public string $code,
        public array $keys,
        public int $line,
        public array $props = [],
    ) {
    }
}
