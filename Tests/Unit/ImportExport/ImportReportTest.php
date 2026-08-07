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

namespace TheliaCMS\Tests\Unit\ImportExport;

use PHPUnit\Framework\TestCase;
use TheliaCMS\ImportExport\ImportReport;

final class ImportReportTest extends TestCase
{
    public function testCountsWhatHappenedToEachKind(): void
    {
        $report = new ImportReport();
        $report->created('pages', 3);
        $report->replaced('pages');
        $report->skipped('menus', 2);

        self::assertSame(['pages' => 3], $report->createdCounts());
        self::assertSame(['pages' => 1], $report->replacedCounts());
        self::assertSame(['menus' => 2], $report->skippedCounts());
    }

    /**
     * A kind that was only skipped still gets a line: "0 created" is the answer
     * to "why is my page not there".
     */
    public function testSummaryHasOneLinePerKind(): void
    {
        $report = new ImportReport();
        $report->created('pages', 2);
        $report->skipped('forms');

        self::assertSame([
            'pages: 2 created, 0 replaced, 0 left alone',
            'forms: 0 created, 0 replaced, 1 left alone',
        ], $report->summary());
    }

    public function testCarriesWarnings(): void
    {
        $report = new ImportReport();

        self::assertFalse($report->hasWarnings());

        $report->warn('hero.jpg is not in the media library.');

        self::assertTrue($report->hasWarnings());
        self::assertSame(['hero.jpg is not in the media library.'], $report->warnings());
    }
}
