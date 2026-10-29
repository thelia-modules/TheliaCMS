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

namespace TheliaCMS\Generation;

/**
 * The generator cannot be called on this site now: the AI layer of the core
 * turned the usage off, has no connection or no model for it, or the budget,
 * the rate or the provider stops it. Nothing the editor can fix in the brief.
 */
final class GenerationUnavailableException extends GenerationException
{
}
