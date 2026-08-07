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
 * Turns one partial and its settings into markup.
 *
 * An interface because the substitution of a page is unit-tested without a
 * theme, a template loader or a cache behind it.
 */
interface PartialFragmentRendererInterface
{
    /**
     * @param array<string, string|int|bool|null> $props already validated against the definition
     */
    public function render(PartialDefinitionInterface $definition, array $props, string $locale, bool $cache = true): string;
}
