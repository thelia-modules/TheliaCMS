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

namespace TheliaCMS\Tests\Integration\Media;

use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Model\BrandImage;
use Thelia\Model\CategoryImage;
use Thelia\Model\ContentImage;
use Thelia\Model\FolderImage;
use Thelia\Model\ProductImage;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Media\CmsMediaLibrary;
use TheliaCMS\Media\ImportCatalogueImagesCommand;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaLibrary\Model\LibraryImage;
use TheliaLibrary\TheliaLibrary;

/**
 * The images of the catalogue copied into the library of the CMS, so a page
 * can show them: with what the catalogue says of them, once.
 */
final class CatalogueImageImportTest extends CmsIntegrationTestCase
{
    /** @var list<string> files written outside the transaction */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->getService(CmsMediaLibrary::class)->images() as $image) {
            $this->files[] = TheliaLibrary::getImageDirectory().$image->getFileName();
        }

        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function testAProductImageIsCopiedWithItsTextsAndTheCatalogueKeepsItsFile(): void
    {
        $image = $this->productImage(['title' => 'Fauteuil Horatio, vue de face', 'alt' => 'Un fauteuil en velours vert', 'locale' => $this->locale()], width: 3);

        $output = $this->import([]);

        self::assertStringContainsString('1 image(s) copied, 0 already in the library.', $output);

        $copied = $this->onlyImage();
        $copied->setLocale($this->locale());
        self::assertSame('Fauteuil Horatio, vue de face', $copied->getTitle());
        self::assertSame('Un fauteuil en velours vert', $copied->getAlt());
        self::assertSame(3, $copied->getWidth(), 'The library measured the copy.');
        self::assertFileExists($image->getUploadDir().\DIRECTORY_SEPARATOR.$image->getFile(), 'The catalogue still shows its own file.');
        self::assertSame([$copied->getId()], array_map(static fn (LibraryImage $found): int => (int) $found->getId(), $this->getService(CmsMediaLibrary::class)->images('Horatio')), 'The CMS offers it.');
    }

    public function testAnImageWithoutATitleIsNamedAfterItsProductAndTaggedWithItsKind(): void
    {
        $this->productImage(['decorative' => true], width: 4, productTitle: 'Tabouret Plink');

        $this->import(['--type' => ['product']]);

        $copied = $this->onlyImage();
        $copied->setLocale($this->locale());
        self::assertSame('Tabouret Plink', $copied->getTitle());
        self::assertSame(1, (int) $copied->getDecorative(), 'Decorative in the catalogue, decorative in the library.');
        self::assertNotSame([], $this->getService(CmsMediaLibrary::class)->tagsOf($copied), 'It says where it comes from.');
    }

    public function testRunAgainItCopiesNothingTwiceAndADryRunCopiesNothing(): void
    {
        $this->productImage([], width: 5);

        self::assertStringContainsString('1 image(s) to copy', $this->import(['--dry-run' => true]));
        self::assertSame([], $this->getService(CmsMediaLibrary::class)->images(), 'A dry run copies nothing.');

        $this->import([]);

        self::assertStringContainsString('0 image(s) copied, 1 already in the library.', $this->import([]));
        self::assertCount(1, $this->getService(CmsMediaLibrary::class)->images());
    }

    public function testACatalogueImageWithoutAFileIsListedAndNotCopied(): void
    {
        $image = $this->productImage([], width: 6);
        unlink($image->getUploadDir().\DIRECTORY_SEPARATOR.$image->getFile());

        $output = $this->import([]);

        self::assertStringContainsString(\sprintf('product image #%d', $image->getId()), $output);
        self::assertStringContainsString('1 without a file.', $output);
        self::assertSame([], $this->getService(CmsMediaLibrary::class)->images());
    }

    /**
     * Each kind of item reaches its images by a relation of its own name: the
     * one of a brand is named after its column.
     */
    public function testTheImagesOfEveryKindOfItemAreCopied(): void
    {
        $fixtures = new FixtureFactory($this->getPropelConnection());
        $folder = $fixtures->folder();
        $items = [
            'category' => [new CategoryImage(), 'setCategoryId', $fixtures->category()],
            'brand' => [new BrandImage(), 'setBrandId', $fixtures->brand()],
            'folder' => [new FolderImage(), 'setFolderId', $folder],
            'content' => [new ContentImage(), 'setContentId', $fixtures->content($folder)],
        ];
        $width = 10;

        foreach ($items as $type => [$image, $setItem, $item]) {
            $item->setLocale($this->locale())->setTitle('Élément '.$type)->save();
            $image->{$setItem}($item->getId());
            $image->setLocale($this->locale())->setFile('import-test-'.$type.'.png')->save();
            $this->writeImage($image->getUploadDir().\DIRECTORY_SEPARATOR.$image->getFile(), ++$width);
        }

        self::assertStringContainsString('4 image(s) copied', $this->import([]));

        $titles = array_map(fn (LibraryImage $image): string => (string) $image->setLocale($this->locale())->getTitle(), $this->getService(CmsMediaLibrary::class)->images());
        sort($titles);
        self::assertSame(['Élément brand', 'Élément category', 'Élément content', 'Élément folder'], $titles);
    }

    public function testAnUnknownTypeIsRefused(): void
    {
        $tester = new CommandTester($this->getService(ImportCatalogueImagesCommand::class));

        self::assertSame(2, $tester->execute(['--type' => ['order']]));
        self::assertStringContainsString('Unknown type order', $tester->getDisplay());
    }

    /**
     * A product image whose file differs from every other by its width: the
     * import tells a file it already holds by its content.
     *
     * @param array<string, mixed> $overrides
     */
    private function productImage(array $overrides, int $width, string $productTitle = 'Produit du test'): ProductImage
    {
        $fixtures = new FixtureFactory($this->getPropelConnection());
        $product = $fixtures->product($fixtures->category(), $fixtures->taxRule(), $fixtures->currency(), ['title' => $productTitle, 'locale' => $this->locale()]);

        $image = $fixtures->productImage($product, $overrides);
        $this->writeImage($image->getUploadDir().\DIRECTORY_SEPARATOR.$image->getFile(), $width);

        return $image;
    }

    private function writeImage(string $path, int $width): void
    {
        if (!is_dir(\dirname($path))) {
            mkdir(\dirname($path), 0o775, true);
        }

        imagepng(imagecreatetruecolor($width, 2), $path);
        $this->files[] = $path;
    }

    private function onlyImage(): LibraryImage
    {
        $images = $this->getService(CmsMediaLibrary::class)->images();
        self::assertCount(1, $images, 'One image in the library.');

        return $images[0];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function import(array $arguments): string
    {
        $tester = new CommandTester($this->getService(ImportCatalogueImagesCommand::class));
        $tester->execute($arguments, ['capture_stderr_separately' => false]);

        return $tester->getDisplay();
    }
}
