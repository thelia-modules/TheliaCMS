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

namespace TheliaCMS\Page;

final class PageTagExistsException extends \RuntimeException
{
    public function __construct(
        public readonly string $name,
    ) {
        parent::__construct(\sprintf('A tag named "%s" exists already.', $name));
    }
}
