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

namespace TheliaCMS\Tests\Unit\Generation\Text;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Generation\Text\TextSanitizer;

final class TextSanitizerTest extends TestCase
{
    public function testKeepsALongTextWhole(): void
    {
        $html = str_repeat('<p>Une description de produit, longue et détaillée.</p>', 600).'<p>La fin du texte.</p>';

        self::assertGreaterThan(20_000, \strlen($html));
        self::assertStringContainsString('La fin du texte.', (new TextSanitizer())->text($html), 'The end of the text was cut off.');
    }
}
