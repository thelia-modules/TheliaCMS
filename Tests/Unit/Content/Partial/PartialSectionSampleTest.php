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

namespace TheliaCMS\Tests\Unit\Content\Partial;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;
use TheliaCMS\Content\Partial\PartialSection;
use TheliaCMS\Partial\PartialProp;
use TheliaCMS\Tests\Unit\Partial\FakePartial;

/**
 * What a block of live content starts with when it is added by hand from the
 * section picker: props the draft accepts, so the section is added at all.
 */
final class PartialSectionSampleTest extends TestCase
{
    public function testARequiredTextWithNoDefaultStartsWithItsLabel(): void
    {
        $section = new PartialSection(new FakePartial('form', [
            PartialProp::text('url', 'Form address', required: true, max: 2048),
        ]), new IdentityTranslator());

        self::assertSame(['url' => 'Form address'], $section->normalizeProps($section->sample()));
    }

    public function testTheLabelIsCutToTheLengthTheTextAllows(): void
    {
        $section = new PartialSection(new FakePartial('form', [
            PartialProp::text('code', 'Code of the campaign', required: true, max: 4),
        ]), new IdentityTranslator());

        self::assertSame(['code' => 'Code'], $section->normalizeProps($section->sample()));
    }

    public function testARequiredTextKeepsItsDefault(): void
    {
        $section = new PartialSection(new FakePartial('header', [
            PartialProp::text('title', 'Title', 'Our stores', required: true),
        ]), new IdentityTranslator());

        self::assertSame(['title' => 'Our stores'], $section->sample());
    }

    public function testAnOptionalTextStaysEmpty(): void
    {
        $section = new PartialSection(new FakePartial('header', [
            PartialProp::text('intro', 'Intro'),
        ]), new IdentityTranslator());

        self::assertSame(['intro' => ''], $section->normalizeProps($section->sample()));
    }
}
