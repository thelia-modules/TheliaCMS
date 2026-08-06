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
 * What a menu entry resolves to in one locale: the text to show, where it goes,
 * and what is wrong with it if anything is.
 */
final readonly class ResolvedTarget
{
    public function __construct(
        public string $label,
        public ?string $url,
        public ?MenuTargetIssue $issue = null,
        /** Title of the pointed row, for the back office to show what is behind the entry. */
        public ?string $targetTitle = null,
    ) {
    }

    public function isUsable(): bool
    {
        return null === $this->issue;
    }
}
