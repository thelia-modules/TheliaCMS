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

namespace TheliaCMS\Tests\Unit\Page\Admin;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use TheliaCMS\Page\Admin\PageDocumentRows;
use TheliaCMS\Page\Admin\PageDocumentSize;
use TheliaCMS\Page\Document\PageDocument;
use TheliaCMS\Page\Document\PageDocumentRepository;
use TheliaCMS\Page\Document\PageDocumentUrl;

/**
 * What the Documents tab shows of the files of a page: the order each arrow
 * would give, the field left empty when the title is only the file name, the
 * size as an editor reads it.
 */
final class PageDocumentRowsTest extends TestCase
{
    public function testEachArrowCarriesTheWholeOrderItWouldGive(): void
    {
        $rows = $this->rows([$this->document(1, 'A.pdf'), $this->document(2, 'B.pdf'), $this->document(3, 'C.pdf')]);

        self::assertNull($rows[0]->moveUp, 'The first document cannot go up.');
        self::assertSame([2, 1, 3], $rows[0]->moveDown);
        self::assertSame([2, 1, 3], $rows[1]->moveUp);
        self::assertSame([1, 3, 2], $rows[1]->moveDown);
        self::assertSame([1, 3, 2], $rows[2]->moveUp);
        self::assertNull($rows[2]->moveDown, 'The last document cannot go down.');
    }

    public function testAloneADocumentCannotMove(): void
    {
        $rows = $this->rows([$this->document(1, 'Seul.pdf')]);

        self::assertNull($rows[0]->moveUp);
        self::assertNull($rows[0]->moveDown);
    }

    public function testATitleThatIsOnlyTheNameOfTheFileLeavesTheFieldEmpty(): void
    {
        $rows = $this->rows([
            $this->document(1, 'Fiche technique.pdf', title: 'Fiche technique'),
            $this->document(2, 'Notice.pdf', title: 'Mode d’emploi', description: 'Pour bien commencer'),
        ]);

        self::assertSame('Fiche technique', $rows[0]->title);
        self::assertSame('', $rows[0]->titleValue, 'Nothing was written: the name is the placeholder.');
        self::assertSame('Mode d’emploi', $rows[1]->titleValue);
        self::assertSame('Pour bien commencer', $rows[1]->description);
        self::assertSame('', $rows[0]->description);
    }

    public function testTheRowCarriesTheAddressOfTheFileAndItsExtensionInCapitals(): void
    {
        $rows = $this->rows([$this->document(7, 'Tarifs.pdf', file: 'tarifs-12345678.pdf', size: 2048)]);

        self::assertSame('/cms/documents/7/tarifs-12345678.pdf', $rows[0]->url);
        self::assertSame('PDF', $rows[0]->extension);
        self::assertSame('Tarifs.pdf', $rows[0]->originalName);
        self::assertSame('2 KB', $rows[0]->size);
    }

    public function testSizesAreReadWithTheDecimalMarkOfTheLanguage(): void
    {
        self::assertSame('0 B', PageDocumentSize::human(0, 'en_US'));
        self::assertSame('850 B', PageDocumentSize::human(850, 'en_US'));
        self::assertSame('12.4 KB', PageDocumentSize::human(12_700, 'en_US'));
        self::assertSame('12,4 KB', PageDocumentSize::human(12_700, 'fr_FR'));
        self::assertSame('100 MB', PageDocumentSize::human(100 * 1024 * 1024, 'en_US'));
        self::assertSame('1.5 MB', PageDocumentSize::human((int) (1.5 * 1024 * 1024), 'en_US'));
        self::assertSame('0 B', PageDocumentSize::human(-5, 'en_US'));
    }

    /**
     * @param list<PageDocument> $documents
     *
     * @return list<\TheliaCMS\Page\Admin\PageDocumentRow>
     */
    private function rows(array $documents): array
    {
        $generator = new class implements UrlGeneratorInterface {
            private RequestContext $context;

            public function setContext(RequestContext $context): void
            {
                $this->context = $context;
            }

            public function getContext(): RequestContext
            {
                return $this->context ??= new RequestContext();
            }

            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return \sprintf('/cms/documents/%d/%s', $parameters['id'], $parameters['file']);
            }
        };

        return (new PageDocumentRows(new PageDocumentRepository(), new PageDocumentUrl($generator)))->present($documents, 'en_US');
    }

    private function document(int $id, string $originalName, ?string $title = null, ?string $description = null, string $file = 'file-00000000.pdf', int $size = 100): PageDocument
    {
        return new PageDocument(
            id: $id,
            title: $title ?? pathinfo($originalName, \PATHINFO_FILENAME),
            description: $description,
            file: $file,
            originalName: $originalName,
            extension: pathinfo($file, \PATHINFO_EXTENSION),
            mimeType: 'application/pdf',
            size: $size,
        );
    }
}
