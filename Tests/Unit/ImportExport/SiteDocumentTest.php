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
use TheliaCMS\ImportExport\ExportFormat;
use TheliaCMS\ImportExport\SiteDocument;

/**
 * An import file comes from outside: from another site, from a colleague, from
 * a backup of unknown age. It is read before anything is written, and it is
 * read defensively.
 */
final class SiteDocumentTest extends TestCase
{
    public function testRefusesAFileThatIsNotAnExport(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not a Thelia CMS export');

        SiteDocument::fromJson('{"format":"wordpress","version":1}');
    }

    public function testRefusesAFormatFromTheFuture(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Update the module first');

        SiteDocument::fromJson('{"format":"thelia-cms","version":'.(ExportFormat::VERSION + 1).'}');
    }

    public function testRefusesBrokenJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not valid JSON');

        SiteDocument::fromJson('{"format":');
    }

    /**
     * A page is created with the real id of its parent, which means the parent
     * has to have been created first, whatever order the file lists them in.
     */
    public function testOrdersPagesParentsFirst(): void
    {
        $document = $this->document([
            'pages' => [
                ['uid' => 'c', 'parent' => 'b'],
                ['uid' => 'a', 'parent' => null],
                ['uid' => 'b', 'parent' => 'a'],
            ],
        ]);

        self::assertSame(['a', 'b', 'c'], array_column($document->pages(), 'uid'));
    }

    public function testKeepsAPageWhoseParentIsNotInTheFile(): void
    {
        $document = $this->document([
            'pages' => [
                ['uid' => 'orphan', 'parent' => 'somewhere-else'],
            ],
        ]);

        self::assertSame(['orphan'], array_column($document->pages(), 'uid'));
        self::assertSame(['orphan'], $document->reparentedPages());
    }

    /**
     * Two pages naming each other as parent cannot both come second. They are
     * imported at the root rather than making the reader loop forever.
     */
    public function testSurvivesPagesPointingAtEachOther(): void
    {
        $document = $this->document([
            'pages' => [
                ['uid' => 'a', 'parent' => 'b'],
                ['uid' => 'b', 'parent' => 'a'],
            ],
        ]);

        self::assertCount(2, $document->pages());
    }

    public function testIgnoresASectionItDoesNotUnderstand(): void
    {
        $document = $this->document(['widgets' => [['code' => 'x']], 'pages' => 'not a list']);

        self::assertSame([], $document->pages());
        self::assertSame([], $document->blocks());
    }

    public function testReadsMediaByIdAndFileName(): void
    {
        $document = $this->document([
            'media' => [
                ['id' => 12, 'file_name' => 'hero.jpg'],
                ['id' => 40, 'file_name' => 'team.png'],
                ['id' => 41],
            ],
        ]);

        self::assertSame([12 => 'hero.jpg', 40 => 'team.png'], $document->media());
    }

    /**
     * @param array<string, mixed> $sections
     */
    private function document(array $sections): SiteDocument
    {
        return SiteDocument::fromArray([
            'format' => ExportFormat::NAME,
            'version' => ExportFormat::VERSION,
            ...$sections,
        ]);
    }
}
