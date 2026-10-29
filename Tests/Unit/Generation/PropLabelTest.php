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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheliaCMS\Generation\Admin\SectionFormBuilder;

/**
 * A prop of the theme that has no label of its own is shown by its name,
 * said the way an editor reads it.
 */
final class PropLabelTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function names(): iterable
    {
        yield 'snake case' => ['items_per_page', 'Items per page'];
        yield 'camel case' => ['itemsPerPage', 'Items per page'];
        yield 'a list of ids is the things it lists' => ['product_ids', 'Products'];
        yield 'the same in camel case' => ['productIds', 'Products'];
        yield 'one id is the thing it points at' => ['category_id', 'Category'];
        yield 'an id alone stays' => ['id', 'Id'];
        yield 'a name that would mislead' => ['max_note', 'Highest score'];
        yield 'a link' => ['href', 'Link'];
        yield 'a plain word' => ['author', 'Author'];
    }

    #[DataProvider('names')]
    public function testTheNameOfAPropReadsAsWords(string $name, string $label): void
    {
        self::assertSame($label, SectionFormBuilder::humanize($name));
    }
}
