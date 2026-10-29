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
            (new SiteCssScoper())->scope("@property --pop { syntax: '<number>'; }\n@font-face { font-family: X; }\n.a { color: red;", 'x');
            self::fail('A stylesheet that reaches past the component is refused.');
        } catch (InvalidSiteComponentException $exception) {
            self::assertCount(3, $exception->problems, implode("\n", $exception->problems));
            self::assertStringContainsString('@property', $exception->problems[0]);
            self::assertStringContainsString('@font-face', $exception->problems[1]);
            self::assertStringContainsString('open', $exception->problems[2]);
        }
    }

    public function testAnAnimationIsNamedAfterTheComponentWhereverItIsUsed(): void
    {
        $css = "@keyframes pan { from { transform: translateX(0); } 50%, to { transform: translateX(-50%); } }\n.track { will-change: transform; }\n@media (prefers-reduced-motion: no-preference) { .track { animation: pan 40s linear infinite, fade 1s; } .track--slow { animation-name: pan; } .track:hover { animation-play-state: paused; } }";

        self::assertSame(
            "@keyframes x--pan {from {transform: translateX(0);}\n50%, to {transform: translateX(-50%);}\n}\n.x .track {will-change: transform;}\n@media (prefers-reduced-motion: no-preference) {.x .track {animation: x--pan 40s linear infinite, fade 1s;}\n.x .track--slow {animation-name: x--pan;}\n.x .track:hover {animation-play-state: paused;}\n}",
            (new SiteCssScoper())->scope($css, 'x'),
            'Two components may both name an animation "pan".',
        );
    }

    public function testAnAnimationMovesOnlyForTheVisitorsWhoDidNotAskForLessMotion(): void
    {
        try {
            (new SiteCssScoper())->scope("@keyframes pan { to { transform: translateX(-50%); } }\n.track { animation: pan 40s linear infinite; }\n@media (min-width: 48rem) { .wide { animation-name: pan; } }\n.still { animation: none; }", 'x');
            self::fail('An animation outside of prefers-reduced-motion: no-preference is refused.');
        } catch (InvalidSiteComponentException $exception) {
            self::assertCount(2, $exception->problems, implode("\n", $exception->problems));
            self::assertStringContainsString('".track"', $exception->problems[0]);
            self::assertStringContainsString('prefers-reduced-motion: no-preference', $exception->problems[0]);
            self::assertStringContainsString('".wide"', $exception->problems[1]);
        }
    }

    public function testAKeyframesHoldsStepsAndAPlainName(): void
    {
        foreach (['@keyframes pan { .a { opacity: 0; } }' => 'step', '@keyframes pan { from { opacity: 0; } to { opacity: 1; }' => 'open', '@keyframes infinite { to { opacity: 0; } }' => '"infinite"', '@keyframes "pan" { to { opacity: 0; } }' => 'name', '@keyframes pan { from { to { opacity: 0; } } }' => 'step'] as $css => $named) {
            try {
                (new SiteCssScoper())->scope($css, 'x');
                self::fail(\sprintf('"%s" is refused.', $css));
            } catch (InvalidSiteComponentException $exception) {
                self::assertStringContainsString($named, $exception->getMessage(), $css);
            }
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
