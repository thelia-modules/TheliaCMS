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

namespace TheliaCMS\Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MapperAwareAssetPackage;
use TheliaCMS\Builder\ThemeCanvas;

/**
 * What a theme declares about the canvas of the editor, read from its
 * `config/theliacms.yaml` and those of its parents.
 */
final class ThemeCanvasTest extends TestCase
{
    /** @var list<string> */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            @unlink($directory.'/'.ThemeCanvas::FILE_NAME);
            @rmdir($directory.'/config');
            @rmdir($directory);
        }
    }

    public function testAThemeWithoutDeclarationChangesNothing(): void
    {
        $canvas = ThemeCanvas::declaredIn([$this->theme(null)]);

        self::assertFalse($canvas->declaresStylesheets());
        self::assertSame([], $canvas->stylesheetUrls($this->packages()));
        self::assertNull($canvas->canvasDocument(), 'GrapesJS keeps writing its own document.');
        self::assertSame([], $canvas->wrapperClasses());
    }

    public function testADeclarationWithoutCanvasKeyChangesNothing(): void
    {
        $canvas = ThemeCanvas::declaredIn([$this->theme("other: value\n")]);

        self::assertFalse($canvas->declaresStylesheets());
        self::assertNull($canvas->canvasDocument());
    }

    public function testTheDeclaredStylesheetsAreKeptInTheirOrder(): void
    {
        $canvas = ThemeCanvas::declaredIn([$this->theme(<<<'YAML'
            canvas:
                stylesheets:
                    - brand/vendor/slider.css
                    - brand/brand.scss
            YAML)]);

        self::assertTrue($canvas->declaresStylesheets());
        self::assertSame(['brand/vendor/slider.css', 'brand/brand.scss'], $canvas->stylesheets);
    }

    public function testALogicalPathIsResolvedToTheNameOfTheLastBuild(): void
    {
        $canvas = new ThemeCanvas(['brand/brand.scss']);

        self::assertSame(
            ['/assets/frontOffice/brand/brand/brand-4f2a9c1e.css'],
            $canvas->stylesheetUrls($this->packages(['brand/brand.scss' => '/assets/frontOffice/brand/brand/brand-4f2a9c1e.css'])),
        );
    }

    public function testTheNameIsResolvedAgainAtEveryCall(): void
    {
        $canvas = new ThemeCanvas(['brand/brand.scss']);

        $before = $canvas->stylesheetUrls($this->packages(['brand/brand.scss' => '/assets/brand-1111.css']));
        $after = $canvas->stylesheetUrls($this->packages(['brand/brand.scss' => '/assets/brand-2222.css']));

        self::assertSame(['/assets/brand-1111.css'], $before);
        self::assertSame(['/assets/brand-2222.css'], $after);
    }

    public function testAUrlIsKeptAsItIs(): void
    {
        $urls = [
            'https://fonts.example.com/css2?family=Brand&display=swap',
            '//cdn.example.com/brand.css',
        ];

        self::assertSame($urls, (new ThemeCanvas($urls))->stylesheetUrls($this->packages()));
    }

    public function testAnEmptyListDeclaresACanvasWithoutThemeStylesheet(): void
    {
        $canvas = ThemeCanvas::declaredIn([$this->theme("canvas:\n    stylesheets: []\n")]);

        self::assertTrue($canvas->declaresStylesheets());
        self::assertSame([], $canvas->stylesheetUrls($this->packages()));
    }

    public function testTheRootClassesDressTheDocumentOfTheCanvas(): void
    {
        $canvas = ThemeCanvas::declaredIn([$this->theme(<<<'YAML'
            canvas:
                html_class: brand
                body_class: "page  page--cms"
                wrapper_class: page__content rich-text
            YAML)]);

        self::assertSame(
            '<!DOCTYPE html><html class="brand"><head></head><body class="page page--cms"></body></html>',
            $canvas->canvasDocument(),
        );
        self::assertSame(['page__content', 'rich-text'], $canvas->wrapperClasses());
    }

    public function testOnlyTheDeclaredRootIsDressed(): void
    {
        $canvas = new ThemeCanvas(htmlClass: 'brand');

        self::assertSame('<!DOCTYPE html><html class="brand"><head></head><body></body></html>', $canvas->canvasDocument());
    }

    public function testAClassCannotBreakOutOfItsAttribute(): void
    {
        $canvas = new ThemeCanvas(bodyClass: '"><script>alert(1)</script>');

        self::assertStringNotContainsString('<script>', (string) $canvas->canvasDocument());
    }

    public function testAChildThemeInheritsWhatItDoesNotDeclare(): void
    {
        $child = $this->theme("canvas:\n    html_class: child\n");
        $parent = $this->theme(<<<'YAML'
            canvas:
                stylesheets: [parent/app.css]
                html_class: parent
                wrapper_class: content
            YAML);

        $canvas = ThemeCanvas::declaredIn([$child, $parent]);

        self::assertSame('child', $canvas->htmlClass, 'The nearest declaration of a key wins.');
        self::assertSame(['parent/app.css'], $canvas->stylesheets);
        self::assertSame(['content'], $canvas->wrapperClasses());
    }

    public function testAChildThemeReplacesTheStylesheetsOfItsParent(): void
    {
        $child = $this->theme("canvas:\n    stylesheets: [child/app.css]\n");
        $parent = $this->theme("canvas:\n    stylesheets: [parent/app.css, parent/print.css]\n");

        self::assertSame(['child/app.css'], ThemeCanvas::declaredIn([$child, $parent])->stylesheets);
    }

    public function testAnUnknownKeyIsReportedRatherThanIgnored(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not know "stylesheet"');

        ThemeCanvas::declaredIn([$this->theme("canvas:\n    stylesheet: brand.css\n")]);
    }

    public function testStylesheetsMustBeAList(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ThemeCanvas::declaredIn([$this->theme("canvas:\n    stylesheets: brand.css\n")]);
    }

    public function testClassesMustBeAString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ThemeCanvas::declaredIn([$this->theme("canvas:\n    html_class: [brand]\n")]);
    }

    public function testAnUnreadableFileIsReported(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a valid YAML file');

        ThemeCanvas::declaredIn([$this->theme("canvas:\n  stylesheets: [a\n")]);
    }

    /**
     * A theme directory, with the given declaration or none.
     */
    private function theme(?string $declaration): string
    {
        $directory = sys_get_temp_dir().'/theliacms-canvas-'.bin2hex(random_bytes(6));
        mkdir($directory.'/config', 0o777, true);
        $this->directories[] = $directory;

        if (null !== $declaration) {
            file_put_contents($directory.'/'.ThemeCanvas::FILE_NAME, $declaration);
        }

        return $directory;
    }

    /**
     * The asset packages as the framework builds them over the asset mapper,
     * which is what `asset()` goes through in the templates of a theme.
     *
     * @param array<string, string> $publicPaths logical path => public path of the last build
     */
    private function packages(array $publicPaths = []): Packages
    {
        $assetMapper = $this->createStub(AssetMapperInterface::class);
        $assetMapper->method('getPublicPath')->willReturnCallback(
            static fn (string $logicalPath): ?string => $publicPaths[$logicalPath] ?? null,
        );

        return new Packages(new MapperAwareAssetPackage(new PathPackage('', new EmptyVersionStrategy()), $assetMapper));
    }
}
