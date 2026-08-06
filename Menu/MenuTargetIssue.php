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

namespace TheliaCMS\Menu;

/**
 * Why a menu entry cannot be shown.
 *
 * An entry carrying an issue is dropped from the front-office menu and reported
 * on the menu screen: a link to a deleted page has to be visible to whoever can
 * fix it, and invisible to visitors.
 */
enum MenuTargetIssue: string
{
    case TargetMissing = 'target_missing';
    case TargetOffline = 'target_offline';
    case TargetHasNoUrl = 'target_has_no_url';
    case AddressMissing = 'address_missing';
    case AddressNotAllowed = 'address_not_allowed';
    case LabelMissing = 'label_missing';

    /**
     * Sentence shown in the back office, translated through the module domain.
     */
    public function message(): string
    {
        return match ($this) {
            self::TargetMissing => 'Its target no longer exists.',
            self::TargetOffline => 'Its target is offline or not published in this language.',
            self::TargetHasNoUrl => 'Its target has no address in this language yet.',
            self::AddressMissing => 'No web address was given.',
            self::AddressNotAllowed => 'This web address is not allowed.',
            self::LabelMissing => 'It has no label in this language, and its target has no title either.',
        };
    }
}
