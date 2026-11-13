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

/**
 * Every call a script of the back office sends says it is one
 * (`X-Requested-With: XMLHttpRequest`). The core keeps the address of every
 * other successful request as the page to come back to
 * (`RequestListener::registerPreviousUrl()`): a call that does not say so
 * becomes that page, and a module that sends the editor back there after a
 * save (SEOne, in the tab of the SEO of the modules) lands on an address that
 * only answers a POST, 405.
 */
final class BackOfficeCallsTest extends TestCase
{
    /** How far after `fetch(` its options are read: the longest call spans a few lines. */
    private const int OPTIONS_LENGTH = 400;

    public function testEveryCallOfTheScriptsSaysItIsOne(): void
    {
        $scripts = glob(\dirname(__DIR__, 3).'/templates/backOffice/default-twig/assets/*.js') ?: [];
        self::assertNotSame([], $scripts, 'The scripts of the back office are found.');

        $calls = 0;
        foreach ($scripts as $script) {
            $source = (string) file_get_contents($script);
            $offset = 0;

            while (false !== ($at = strpos($source, 'fetch(', $offset))) {
                ++$calls;
                $line = substr_count($source, "\n", 0, $at) + 1;
                self::assertStringContainsString(
                    'X-Requested-With',
                    substr($source, $at, self::OPTIONS_LENGTH),
                    \sprintf('%s, line %d: a call without X-Requested-With becomes the page the editor comes back to.', basename($script), $line),
                );
                $offset = $at + 1;
            }
        }

        self::assertGreaterThan(0, $calls, 'The scripts send calls: the test reads something.');
    }
}
