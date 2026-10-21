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
 * A component the site cannot keep: a code taken or malformed, props that do
 * not read, a sample its props refuse, a template the sandbox refuses. Every
 * problem is listed, so the model that wrote it is told everything at once.
 */
final class InvalidSiteComponentException extends \InvalidArgumentException
{
    /**
     * @param list<string> $problems
     */
    private function __construct(
        public readonly array $problems,
    ) {
        parent::__construct(implode(' ', $problems));
    }

    /**
     * @param list<string> $problems
     */
    public static function because(array $problems): self
    {
        return new self([] === $problems ? ['The component is invalid.'] : $problems);
    }
}
