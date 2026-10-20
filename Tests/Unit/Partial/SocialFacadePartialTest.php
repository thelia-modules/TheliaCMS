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

namespace TheliaCMS\Tests\Unit\Partial;

use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\Partial\Definition\SocialFacadePartial;

/**
 * What a post becomes on its way to the theme: a full address stays whole, an
 * identifier loses whatever could not belong in a URL.
 */
final class SocialFacadePartialTest extends TestCase
{
    public function testAFullAddressIsKeptAsItIsGiven(): void
    {
        $address = 'https://www.facebook.com/shop/posts/pfbid02abc?ref=embed';

        $context = $this->partial()->context(['network' => 'facebook', 'post' => $address, 'summary' => null], 'fr_FR');

        self::assertSame($address, $context['page_url']);
    }

    public function testAnIdentifierIsStrippedOfWhatCannotBeInAUrl(): void
    {
        $context = $this->partial()->context(['network' => 'instagram', 'post' => 'C1a2b3<script>', 'summary' => null], 'fr_FR');

        self::assertSame('https://www.instagram.com/p/C1a2b3script/', $context['page_url']);
    }

    private function partial(): SocialFacadePartial
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new SocialFacadePartial($translator);
    }
}
