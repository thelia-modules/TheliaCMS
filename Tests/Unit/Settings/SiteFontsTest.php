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

namespace TheliaCMS\Tests\Unit\Settings;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use TheliaCMS\Settings\SiteFonts;

final class SiteFontsTest extends TestCase
{
    private string $directory;
    private SiteFonts $fonts;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/cms-fonts-test-'.bin2hex(random_bytes(4));
        mkdir($this->directory, 0o755, true);
        $this->fonts = new SiteFonts($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    public function testAFontIsStoredUnderANameSafeForCssAndUrls(): void
    {
        $stored = $this->fonts->store($this->upload('Ma Police (Titres) !.woff2'));

        self::assertSame('Ma-Police-Titres.woff2', $stored);
        self::assertNotNull($this->fonts->path($stored));
    }

    public function testStoringTheSameNameTwiceKeepsBothFiles(): void
    {
        $first = $this->fonts->store($this->upload('Recoleta.woff2'));
        $second = $this->fonts->store($this->upload('Recoleta.woff2'));

        self::assertSame('Recoleta.woff2', $first);
        self::assertSame('Recoleta-2.woff2', $second);
        self::assertCount(2, $this->fonts->all());
    }

    public function testAFileThatIsNotAWoff2IsRefusedWhateverItsName(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->fonts->store($this->upload('sournois.woff2', '<svg onload="alert(1)">'));
    }

    public function testANameNeverReachesOutsideTheDirectory(): void
    {
        self::assertNull($this->fonts->path('../../../etc/passwd'));
        self::assertNull($this->fonts->path('font.ttf'));
        self::assertFalse($this->fonts->has('..%2F..%2Fpasswd.woff2'));
    }

    public function testDeleteOnlyTouchesWhatItOwns(): void
    {
        $stored = $this->fonts->store($this->upload('Recoleta.woff2'));

        $this->fonts->delete('../'.basename($this->directory).'/'.$stored);
        self::assertNotNull($this->fonts->path($stored), 'a path was accepted where a name was expected');

        $this->fonts->delete($stored);
        self::assertNull($this->fonts->path($stored));
    }

    private function upload(string $name, string $content = "wOF2\x00fake-font-data"): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'font');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'font/woff2', null, true);
    }
}
