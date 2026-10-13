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

namespace TheliaCMS\Tests\Unit\Page;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Page\PageTagName;

/**
 * The name of a tag is kept as typed, less the spaces nobody meant.
 */
final class PageTagNameTest extends TestCase
{
    public function testTheSpacesAroundAndTheRunsOfSpacesInsideAreDropped(): void
    {
        self::assertSame("Recette d'été", PageTagName::normalize("  Recette   d'été \n"));
    }

    public function testTheCaseAndTheAccentsAreKept(): void
    {
        self::assertSame('Été', PageTagName::normalize('Été'));
    }

    public function testAnEmptyNameIsRefused(): void
    {
        self::assertFalse(PageTagName::isValid(''));
        self::assertFalse(PageTagName::isValid("  \t "));
    }

    public function testANameLongerThanTheColumnIsRefusedAndOneThatFitsIsAccepted(): void
    {
        self::assertTrue(PageTagName::isValid(str_repeat('é', PageTagName::MAX_LENGTH)), 'Measured in characters, not in bytes.');
        self::assertFalse(PageTagName::isValid(str_repeat('a', PageTagName::MAX_LENGTH + 1)));
    }
}
