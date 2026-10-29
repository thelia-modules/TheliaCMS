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

namespace TheliaCMS\Tests\Unit\Generation;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Generation\Admin\PreviewWidths;

/**
 * The widths the preview of the content screen offers: the breakpoints of
 * the theme the toolkit names, from the nearest tokens file of the chain.
 */
final class PreviewWidthsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/theliacms-preview-widths-'.bin2hex(random_bytes(4));
        mkdir($this->directory.'/child/assets/styles', 0o777, true);
        mkdir($this->directory.'/parent/assets/styles', 0o777, true);
        mkdir($this->directory.'/bare', 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (['child', 'parent'] as $theme) {
            @unlink($this->directory.'/'.$theme.'/'.PreviewWidths::VARIABLES_FILE);
            @rmdir($this->directory.'/'.$theme.'/assets/styles');
            @rmdir($this->directory.'/'.$theme.'/assets');
            @rmdir($this->directory.'/'.$theme);
        }

        @rmdir($this->directory.'/bare');
        @rmdir($this->directory);
    }

    public function testTheNearestTokensFileOfTheChainGivesTheNamedBreakpointsNarrowestFirst(): void
    {
        file_put_contents($this->directory.'/child/'.PreviewWidths::VARIABLES_FILE, "@theme {\n  --breakpoint-md: 48rem;\n  --breakpoint-xs: 24.375rem;\n  --breakpoint-3xl: 120rem;\n}\n");
        file_put_contents($this->directory.'/parent/'.PreviewWidths::VARIABLES_FILE, "@theme {\n  --breakpoint-lg: 64rem;\n}\n");

        self::assertSame(
            [['label' => 'Mobile M', 'px' => 390], ['label' => 'Tablet', 'px' => 768]],
            PreviewWidths::fromThemeDirectories([$this->directory.'/child', $this->directory.'/parent']),
            'The child redefines the breakpoints; an unnamed one (3xl) is not offered, as in the toolkit.',
        );
    }

    public function testAThemeWithoutTokensGetsTheBreakpointsOfFlexy(): void
    {
        $widths = PreviewWidths::fromThemeDirectories([$this->directory.'/bare']);

        self::assertSame(['Mobile S', 'Mobile M', 'Mobile L', 'Tablet', 'Small Desktop', 'Desktop', 'Large Desktop'], array_column($widths, 'label'));
        self::assertSame([320, 390, 640, 768, 1024, 1366, 1680], array_column($widths, 'px'));
    }
}
