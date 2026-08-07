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

namespace TheliaCMS\Tests\Unit\Script;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Script\ConsentGate;

/**
 * Getting this wrong loads a third-party tag on a visitor who refused it, which
 * is the one thing the consent screen exists to prevent.
 */
final class ConsentGateTest extends TestCase
{
    public function testASnippetWithNoCategoryIsWrittenAsItWasTyped(): void
    {
        $snippet = '<script>console.log(1)</script>';

        self::assertSame($snippet, ConsentGate::wrap($snippet, null));
        self::assertSame($snippet, ConsentGate::wrap($snippet, ''));
        self::assertSame($snippet, ConsentGate::wrap($snippet, '   '));
    }

    public function testASnippetWithACategoryIsHeldInAnInertTemplate(): void
    {
        $wrapped = ConsentGate::wrap('<script>track()</script>', 'google_analytics');

        self::assertSame(
            '<template data-cms-consent="google_analytics"><script>track()</script></template>',
            $wrapped,
        );
    }

    /**
     * A pixel is the tag that needed consent the most, and marking only the
     * script tags inert would leave it free to fire.
     */
    public function testEverythingInTheSnippetIsHeldBackNotOnlyItsScripts(): void
    {
        $wrapped = ConsentGate::wrap('<img src="https://vendor.example/p.gif" alt="">', 'vendor');

        self::assertStringStartsWith('<template data-cms-consent="vendor">', $wrapped);
        self::assertStringEndsWith('</template>', $wrapped);
    }

    /**
     * The category is typed by hand in the back office, so it reaches the page
     * as an attribute value and has to be escaped like one.
     */
    public function testTheCategoryCannotBreakOutOfItsAttribute(): void
    {
        $wrapped = ConsentGate::wrap('<script></script>', '" onload="alert(1)');

        self::assertStringNotContainsString('onload="alert(1)"', $wrapped);
        self::assertStringContainsString('&quot; onload=&quot;alert(1)', $wrapped);
    }
}
