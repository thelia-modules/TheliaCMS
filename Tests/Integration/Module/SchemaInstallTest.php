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

namespace TheliaCMS\Tests\Integration\Module;

use TheliaCMS\Model\CmsForm;
use TheliaCMS\Model\CmsFormQuery;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\TheliaCMS;

/**
 * The first activation on tables somebody else already created.
 *
 * A fresh install of Thelia creates the tables of every module it finds, and
 * plays their update files, before any module is activated: the contact form
 * every site starts with is seeded there. The first `postActivation()` that
 * follows must not play the schema again, since `TheliaMain.sql` drops each
 * table before creating it: the form was gone on every new shop.
 *
 * Runs inside the transaction of the test: on tables that exist, the
 * activation writes rows and no schema, so everything it does is rolled back.
 */
final class SchemaInstallTest extends CmsIntegrationTestCase
{
    private ?string $previousState = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousState = TheliaCMS::getConfigValue('is_initialized');
    }

    protected function tearDown(): void
    {
        TheliaCMS::setConfigValue('is_initialized', $this->previousState ?? '1');

        parent::tearDown();
    }

    public function testTheFirstActivationKeepsTheRowsOfTablesThatAlreadyExist(): void
    {
        $probe = (new CmsForm())->setCode('probe-'.bin2hex(random_bytes(4)));
        $probe->save();

        // The state a fresh install leaves: tables and seed rows, never initialised.
        TheliaCMS::setConfigValue('is_initialized', '0');

        (new TheliaCMS())->postActivation($this->getPropelConnection());

        self::assertNotNull(
            CmsFormQuery::create()->findOneByCode((string) $probe->getCode()),
            'The activation dropped the tables the install had filled.',
        );
        self::assertSame('1', (string) TheliaCMS::getConfigValue('is_initialized'));
    }

    /**
     * A site whose state was lost but whose pages were not (a module config
     * purged by hand, a restored dump) is not handed the sample legal pages a
     * second time: four more drafts named like the ones it has would take the
     * next free addresses.
     */
    public function testAFirstActivationOnASiteWithPagesSeedsNoLegalPage(): void
    {
        $this->createPage('Mentions legales du site');
        $before = CmsPageQuery::create()->count();

        TheliaCMS::setConfigValue('is_initialized', '0');
        (new TheliaCMS())->postActivation($this->getPropelConnection());

        self::assertSame($before, CmsPageQuery::create()->count());
    }
}
