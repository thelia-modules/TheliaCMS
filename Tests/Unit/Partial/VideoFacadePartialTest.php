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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\Partial\Definition\VideoFacadePartial;

/**
 * What a theme is handed to draw a video: the address of the player, the
 * page of the video, and the platform both as a name to print and as a code
 * to tell a player.
 */
final class VideoFacadePartialTest extends TestCase
{
    public function testTheThemeIsToldThePlatformByNameAndByCode(): void
    {
        $context = $this->partial()->context(['provider' => 'vimeo', 'video' => '123456', 'title' => 'The tour', 'poster' => null], 'fr_FR');

        self::assertSame('Vimeo', $context['provider']);
        self::assertSame('vimeo', $context['provider_code']);
        self::assertSame('https://player.vimeo.com/video/123456?autoplay=1', $context['embed_url']);
        self::assertSame('https://vimeo.com/123456', $context['page_url']);
        self::assertNull($context['poster_url']);
    }

    public function testAnUnknownPlatformFallsBackToYoutube(): void
    {
        $context = $this->partial()->context(['provider' => 'elsewhere', 'video' => 'abc', 'title' => 'T', 'poster' => 7], 'fr_FR');

        self::assertSame('youtube', $context['provider_code']);
        self::assertSame('https://www.youtube-nocookie.com/embed/abc?autoplay=1', $context['embed_url']);
        self::assertSame('/image-library/7/full/960,!/0/default.webp', $context['poster_url']);
    }

    private function partial(): VideoFacadePartial
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route): string => '/'.$route);

        return new VideoFacadePartial($translator, $urls);
    }
}
