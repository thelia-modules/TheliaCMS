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

namespace TheliaCMS\Partial;

/**
 * A partial block was placed in a page without the setting it cannot work
 * without — a menu block naming no menu, for instance.
 *
 * On the front office the block is simply left out; in the editor the message
 * is what tells the author which setting is missing.
 */
final class MissingPartialPropException extends \RuntimeException
{
}
