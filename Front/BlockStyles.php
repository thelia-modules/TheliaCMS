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

namespace TheliaCMS\Front;

/**
 * The stylesheet that gives the blocks of the catalogue their look.
 *
 * Shipped with the module rather than left to the theme: the module is what
 * renders the `cms-*` markup, so it owes a page that reads as a page on any
 * theme. A theme states the four accent tokens and overrides whatever else it
 * wants, and its own stylesheet is linked after this one.
 */
final readonly class BlockStyles
{
    private const string FILE = 'assets/blocks.css';

    public function path(): string
    {
        return \dirname(__DIR__).'/'.self::FILE;
    }

    /**
     * Names the content of the file, so the address changes when the file does
     * and never otherwise: what is served under one address is immutable and
     * cached for a year.
     */
    public function version(): string
    {
        $path = $this->path();

        if (!is_file($path)) {
            return '0';
        }

        return substr((string) hash_file('xxh3', $path), 0, 12);
    }
}
