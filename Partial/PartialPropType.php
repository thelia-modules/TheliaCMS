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
 * The kinds of setting a partial block can expose in the editor.
 *
 * Deliberately few: every value stored in a page is typed here, so a template
 * receives a string, an integer or a boolean and never whatever the browser
 * put in the attribute.
 */
enum PartialPropType: string
{
    case Text = 'text';
    case Integer = 'integer';
    case Boolean = 'boolean';
    /** One of a fixed list of values. */
    case Choice = 'choice';
    /** The id of a record picked from an endpoint (a page, a menu, a block). */
    case Reference = 'reference';
}
