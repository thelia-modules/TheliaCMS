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

namespace TheliaCMS\Tests\Unit\Menu;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Menu\Admin\UniqueViolation;

final class UniqueViolationTest extends TestCase
{
    public function testAnIntegrityFailureOfPdoIsRecognisedThroughItsWrappers(): void
    {
        $pdo = new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry');
        $pdo->errorInfo = ['23000', 1062, 'Duplicate entry'];

        self::assertTrue(UniqueViolation::caused($pdo));
        self::assertTrue(UniqueViolation::caused(new \RuntimeException('Unable to execute INSERT', 0, new \RuntimeException('wrapped', 0, $pdo))));
    }

    public function testAMessageOfTheDatabaseIsEnoughWhenTheDriverGaveNoCode(): void
    {
        self::assertTrue(UniqueViolation::caused(new \RuntimeException("Duplicate entry 'main-' for key 'unq_cms_menu_code_locale'")));
    }

    public function testAnotherFailureIsNotMistakenForIt(): void
    {
        self::assertFalse(UniqueViolation::caused(new \RuntimeException('Connection lost', 0, new \LogicException('other'))));
    }
}
