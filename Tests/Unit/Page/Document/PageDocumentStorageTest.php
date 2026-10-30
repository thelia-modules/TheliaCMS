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

namespace TheliaCMS\Tests\Unit\Page\Document;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheliaCMS\Page\Document\PageDocumentStorage;

/**
 * A stored name is readable, never guessed, and the only shape a path is
 * built from: whatever the database or an address holds cannot leave the
 * directory of the documents.
 */
final class PageDocumentStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/theliacms-page-documents-'.bin2hex(random_bytes(4)).'/';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testTheStoredNameIsTheSlugOfTheNameThenEightRandomDigits(): void
    {
        $name = $this->storage()->nameFor('Fiche Technique Été 2026.PDF', 'PDF');

        self::assertMatchesRegularExpression('/^fiche-technique-ete-2026-[0-9a-f]{8}\.pdf$/D', $name);
        self::assertNotSame($name, $this->storage()->nameFor('Fiche Technique Été 2026.PDF', 'pdf'), 'Two files sent under one name are two files.');
    }

    public function testANameWithNothingToKeepGetsAPlaceholderSlug(): void
    {
        self::assertMatchesRegularExpression('/^document-[0-9a-f]{8}\.txt$/D', $this->storage()->nameFor('???.txt', 'txt'));
        self::assertMatchesRegularExpression('/^document-[0-9a-f]{8}\.zip$/D', $this->storage()->nameFor('.zip', 'zip'));
    }

    public function testTheSlugIsBounded(): void
    {
        $name = $this->storage()->nameFor(str_repeat('a-', 200).'.pdf', 'pdf');

        self::assertLessThanOrEqual(PageDocumentStorage::SLUG_MAX_LENGTH + 13, \strlen($name));
        self::assertTrue(PageDocumentStorage::isStoredName($name), 'The cut leaves no dash at the end of the slug.');
    }

    public function testAnExtensionOutOfTheListIsNeverStored(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->storage()->nameFor('page.html', 'html');
    }

    public function testEveryNameTheStorageWritesIsOneItReads(): void
    {
        foreach (['Tarifs.xlsx', 'Notice d\'utilisation (v2).docx', '../../etc/passwd.txt', 'Ωmega.odt', 'a.b.c.pptx'] as $original) {
            $name = $this->storage()->nameFor($original, pathinfo($original, \PATHINFO_EXTENSION));

            self::assertTrue(PageDocumentStorage::isStoredName($name), $name);
            self::assertSame($this->directory.$name, $this->storage()->path($name));
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function namesOutOfShape(): iterable
    {
        yield 'a parent directory' => ['../fiche-0123abcd.pdf'];
        yield 'a parent directory in the slug' => ['..-0123abcd.pdf'];
        yield 'a sub directory' => ['cms/fiche-0123abcd.pdf'];
        yield 'a backslash' => ['..\\fiche-0123abcd.pdf'];
        yield 'an absolute path' => ['/etc/passwd'];
        yield 'a null byte' => ["fiche-0123abcd.pdf\0.php"];
        yield 'a new line after the name' => ["fiche-0123abcd.pdf\n"];
        yield 'an extension out of the list' => ['fiche-0123abcd.php'];
        yield 'a double extension' => ['fiche-0123abcd.php.pdf'];
        yield 'no random suffix' => ['fiche.pdf'];
        yield 'a short suffix' => ['fiche-0123abc.pdf'];
        yield 'upper case' => ['Fiche-0123abcd.pdf'];
        yield 'a hidden file' => ['.htaccess'];
        yield 'empty' => [''];
    }

    #[DataProvider('namesOutOfShape')]
    public function testANameOutOfShapeIsNeverAPath(string $name): void
    {
        self::assertFalse(PageDocumentStorage::isStoredName($name));
        self::assertFalse($this->storage()->exists($name));

        $this->expectException(\InvalidArgumentException::class);

        $this->storage()->path($name);
    }

    public function testACopyIsAnotherFileUnderAnotherName(): void
    {
        $storage = $this->storage();
        $name = $storage->nameFor('Tarifs 2026.pdf', 'pdf');
        mkdir($this->directory, 0o775, true);
        file_put_contents($storage->path($name), '%PDF-1.4 tarifs');

        $copy = $storage->copy($name);

        self::assertNotSame($name, $copy);
        self::assertMatchesRegularExpression('/^tarifs-2026-[0-9a-f]{8}\.pdf$/D', $copy);
        self::assertSame('%PDF-1.4 tarifs', file_get_contents($storage->path($copy)));

        $storage->delete($name);

        self::assertFalse($storage->exists($name));
        self::assertTrue($storage->exists($copy), 'Deleting one leaves the other.');
    }

    public function testDeletingAFileAlreadyGoneIsNotAnError(): void
    {
        $this->storage()->delete('gone-0123abcd.pdf');

        $this->addToAssertionCount(1);
    }

    private function storage(): PageDocumentStorage
    {
        return new PageDocumentStorage($this->directory);
    }
}
