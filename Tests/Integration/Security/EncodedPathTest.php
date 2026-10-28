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

namespace TheliaCMS\Tests\Integration\Security;

use Thelia\Core\Security\AccessManager;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Security\CmsResources;

/**
 * The router decodes the path once before it matches a route, so an address
 * with one letter percent-encoded (`/admin/c%6Ds/forms`) reaches the same
 * controller as the plain one. The guard of the back office has to read the
 * path the same way, or encoding a letter walks past it.
 *
 * The proxy in front of a site may normalise such a path before PHP sees it,
 * and may not: these requests reach the kernel as the client wrote them.
 */
final class EncodedPathTest extends AdminScreenTestCase
{
    public function testAnAnonymousVisitorIsRefusedAnEncodedAddress(): void
    {
        $this->logOut();

        self::assertSame(403, $this->send('GET', '/admin/cms/forms'), 'The plain address is refused.');
        self::assertSame(403, $this->send('GET', '/admin/c%6Ds/forms'), 'An encoded letter in the prefix changes nothing.');
        self::assertSame(403, $this->send('GET', '/admin/%63ms/settings'));
        self::assertSame(403, $this->send('GET', '/admin%2Fcms/forms'), 'Nor does an encoded slash.');
    }

    public function testAnEncodedSectionIsGuardedByItsOwnRight(): void
    {
        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([
            CmsResources::PAGE => [AccessManager::VIEW],
        ]));

        self::assertSame(403, $this->send('GET', '/admin/cms/settings'), 'The settings are not a page.');
        self::assertSame(403, $this->send('GET', '/admin/cms/%73ettings'), 'Encoding the section does not make it one.');
    }

    public function testAHiddenScreenStaysHiddenUnderAnEncodedAddress(): void
    {
        self::assertSame(404, $this->send('GET', '/admin/cms/%73cripts'));
    }
}
