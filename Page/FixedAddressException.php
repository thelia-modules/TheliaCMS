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

/**
 * The fixed address typed for a page cannot be one: malformed, reserved, or
 * the address of another object of the site.
 */
final class FixedAddressException extends \InvalidArgumentException
{
}
