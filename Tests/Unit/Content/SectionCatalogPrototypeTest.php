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

namespace TheliaCMS\Tests\Unit\Content;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Content\PropSchema;
use TheliaCMS\Content\RichTextSection;
use TheliaCMS\Content\SectionCatalog;
use TheliaCMS\Content\SectionProviderInterface;
use TheliaCMS\Content\Site\SiteComponentSection;
use TheliaCMS\Content\Site\SiteTemplateRenderer;
use TheliaCMS\Tests\Unit\Content\Site\PriceTable;

final class SectionCatalogPrototypeTest extends TestCase
{
    public function testAPrototypeIsInTheCatalogueAndOfferedToNobody(): void
    {
        $kept = new SiteComponentSection(PriceTable::component(), new PropSchema([]), new SiteTemplateRenderer());
        $prototype = PriceTable::component();
        $prototype = new SiteComponentSection(
            new \TheliaCMS\Content\Site\SiteComponent('Timeline', 'Timeline', 'Steps.', $prototype->props, $prototype->sample, $prototype->template),
            new PropSchema([]),
            new SiteTemplateRenderer(),
            prototype: true,
        );
        $provider = new class($kept, $prototype) implements SectionProviderInterface {
            public function __construct(private readonly SiteComponentSection $kept, private readonly SiteComponentSection $prototype)
            {
            }

            public function sections(): iterable
            {
                yield $this->kept;
                yield $this->prototype;
            }
        };

        $catalog = new SectionCatalog([new RichTextSection()], [$provider]);

        self::assertSame([RichTextSection::NAME, 'Site:PriceTable', 'Site:Timeline'], $catalog->names(), 'A document may name it: it renders and validates.');
        self::assertSame([RichTextSection::NAME, 'Site:PriceTable'], $catalog->offeredNames(), 'The generator and the content screen of another page are not offered it.');
        self::assertSame($prototype, $catalog->find('Site:Timeline'));
    }
}
