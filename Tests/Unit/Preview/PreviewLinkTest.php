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

namespace TheliaCMS\Tests\Unit\Preview;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use TheliaCMS\Preview\PreviewLink;

/**
 * A preview link is handed to a client who is not logged in, so the signature is
 * the only thing standing between them and every unpublished draft on the site.
 */
final class PreviewLinkTest extends TestCase
{
    private const string SECRET = 'a-secret-nobody-else-has';

    private PreviewLink $links;

    protected function setUp(): void
    {
        $this->links = new PreviewLink($this->urlGenerator(), self::SECRET);
    }

    public function testSignsTheLinkItGenerates(): void
    {
        $url = $this->links->urlFor(12, 'fr_FR', new \DateTimeImmutable('2026-01-01 12:00:00'));

        $parameters = $this->parametersOf($url);

        self::assertSame('12', $parameters['id']);
        self::assertSame('fr_FR', $parameters['locale']);
        self::assertTrue($this->links->isValid(
            12,
            'fr_FR',
            (int) $parameters['expires'],
            $parameters['signature'],
            new \DateTimeImmutable('2026-01-01 12:00:00'),
        ));
    }

    public function testTheLinkLastsSeventyTwoHours(): void
    {
        $issuedAt = new \DateTimeImmutable('2026-01-01 12:00:00');
        $parameters = $this->parametersOf($this->links->urlFor(12, 'fr_FR', $issuedAt));

        self::assertSame($issuedAt->getTimestamp() + 72 * 3600, (int) $parameters['expires']);
    }

    public function testRejectsAnExpiredLink(): void
    {
        $issuedAt = new \DateTimeImmutable('2026-01-01 12:00:00');
        $parameters = $this->parametersOf($this->links->urlFor(12, 'fr_FR', $issuedAt));

        self::assertFalse($this->links->isValid(
            12,
            'fr_FR',
            (int) $parameters['expires'],
            $parameters['signature'],
            $issuedAt->modify('+73 hours'),
        ));
    }

    public function testRejectsAForgedSignature(): void
    {
        $now = new \DateTimeImmutable('2026-01-01 12:00:00');
        $expires = $now->getTimestamp() + 3600;

        self::assertFalse($this->links->isValid(12, 'fr_FR', $expires, str_repeat('0', 64), $now));
        self::assertFalse($this->links->isValid(12, 'fr_FR', $expires, '', $now));
    }

    /**
     * @return iterable<string, array{0: int, 1: string, 2: int}>
     */
    public static function tamperedParameters(): iterable
    {
        yield 'another page' => [13, 'fr_FR', 0];
        yield 'another locale' => [12, 'en_US', 0];
        yield 'a later expiry' => [12, 'fr_FR', 86400];
    }

    /**
     * Every signed value has to be checked, or the link to one draft opens
     * another — or never expires.
     */
    #[DataProvider('tamperedParameters')]
    public function testRejectsTamperedParameters(int $pageId, string $locale, int $extraSeconds): void
    {
        $now = new \DateTimeImmutable('2026-01-01 12:00:00');
        $parameters = $this->parametersOf($this->links->urlFor(12, 'fr_FR', $now));

        self::assertFalse($this->links->isValid(
            $pageId,
            $locale,
            (int) $parameters['expires'] + $extraSeconds,
            $parameters['signature'],
            $now,
        ));
    }

    /**
     * Signatures are derived from the application secret, so a link minted on
     * one installation opens nothing on another.
     */
    public function testALinkFromAnotherInstallationDoesNotOpen(): void
    {
        $now = new \DateTimeImmutable('2026-01-01 12:00:00');
        $elsewhere = new PreviewLink($this->urlGenerator(), 'a-different-secret');

        $parameters = $this->parametersOf($elsewhere->urlFor(12, 'fr_FR', $now));

        self::assertFalse($this->links->isValid(
            12,
            'fr_FR',
            (int) $parameters['expires'],
            $parameters['signature'],
            $now,
        ));
    }

    /**
     * @return array<string, string>
     */
    private function parametersOf(string $url): array
    {
        /** @var array<string, string> $parameters */
        $parameters = [];
        parse_str(parse_url($url, \PHP_URL_QUERY) ?: '', $parameters);

        return $parameters;
    }

    private function urlGenerator(): UrlGeneratorInterface
    {
        return new class implements UrlGeneratorInterface {
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return 'https://shop.test/'.$name.'?'.http_build_query($parameters);
            }

            public function setContext(RequestContext $context): void
            {
            }

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }
        };
    }
}
