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

namespace TheliaCMS\Content;

/**
 * A section that draws its example in a way of its own, which a document
 * could not store: what the toolkit shows when no props are given.
 */
interface ExampleRenderingInterface
{
    public function renderExample(string $locale): string;
}
