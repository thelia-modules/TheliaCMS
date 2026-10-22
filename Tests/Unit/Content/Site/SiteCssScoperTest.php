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

namespace TheliaCMS\Tests\Unit\Content\Site;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Content\Site\InvalidSiteComponentException;
use TheliaCMS\Content\Site\SiteCssScoper;

/**
 * The stylesheet of a component, kept to the component.
 */
final class SiteCssScoperTest extends TestCase
{
    public function testEverySelectorIsPutUnderTheClassOfTheComponent(): void
    {
        $css = "/* plans */ .plan, h3:is(.a, .b) { color: var(--color-primary); }\n:scope { display: grid; }\n& > .plan--featured { order: -1 }\n@media (min-width: 48rem) { .plans { grid-template-columns: repeat(3, 1fr); } }";

        self::assertSame(
            ".x .plan, .x h3:is(.a, .b) {color: var(--color-primary);}\n.x {display: grid;}\n.x > .plan--featured {order: -1}\n@media (min-width: 48rem) {.x .plans {grid-template-columns: repeat(3, 1fr);}\n}",
            (new SiteCssScoper())->scope($css, 'x'),
        );
    }

    public function testAnAtRuleOfTheWholePageIsRefusedAndSoIsABraceLeftOpen(): void
    {
        try {
            (new SiteCssScoper())->scope("@keyframes pop { from { opacity: 0 } }\n@font-face { font-family: X; }\n.a { color: red;", 'x');
            self::fail('A stylesheet that reaches past the component is refused.');
        } catch (InvalidSiteComponentException $exception) {
            self::assertCount(3, $exception->problems, implode("\n", $exception->problems));
            self::assertStringContainsString('@keyframes', $exception->problems[0]);
            self::assertStringContainsString('@font-face', $exception->problems[1]);
            self::assertStringContainsString('open', $exception->problems[2]);
        }
    }

    public function testASelectorCannotReachPastTheComponent(): void
    {
        foreach ([':not(:scope) a { color: red }', '.a:is(:scope) b { color: red }', '& ~ div { display: none }', '& + p { display: none }', ':scope~div { display: none }'] as $css) {
            try {
                (new SiteCssScoper())->scope($css, 'x');
                self::fail(\sprintf('"%s" is refused.', $css));
            } catch (InvalidSiteComponentException $exception) {
                self::assertStringContainsString('scope', $exception->getMessage());
            }
        }
    }

    public function testACommaInAnAttributeSelectorOrAStringDoesNotSplitTheSelector(): void
    {
        self::assertSame('.x [title="a,b"], .x .c {color: red;}', (new SiteCssScoper())->scope('[title="a,b"], .c { color: red; }', 'x'));
    }

    public function testABraceClosedTwiceIsRefused(): void
    {
        $this->expectException(InvalidSiteComponentException::class);

        (new SiteCssScoper())->scope('.a { color: red; } }', 'x');
    }
}
