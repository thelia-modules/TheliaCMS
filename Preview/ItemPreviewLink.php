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

namespace TheliaCMS\Preview;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TheliaCMS\Catalogue\CatalogueItem;
use TheliaCMS\Catalogue\CatalogueItemType;

/**
 * Shareable links to the draft of the document of a catalogue item, signed
 * like the link to the draft of a page (PreviewLink) under salts of their
 * own, so no signature opens another:
 *
 * - the draft alone, on a page of the module (`urlFor()`);
 * - the page of the item itself, with the draft in place of the published
 *   document (`pageUrlFor()`), read back by ItemDraftRequest.
 */
final readonly class ItemPreviewLink
{
    /** The query parameter the page of the item carries the token in. */
    public const string PAGE_PARAMETER = 'cms_preview';

    private const string ROUTE = 'cms.item.preview';

    private const string SALT = 'thelia-cms.item-preview';

    private const string PAGE_SALT = 'thelia-cms.item-page-preview';

    public function __construct(
        private UrlGeneratorInterface $urls,
        #[Autowire('%kernel.secret%')]
        private string $applicationSecret,
    ) {
    }

    public function urlFor(CatalogueItemType $type, int $id, string $locale, ?\DateTimeImmutable $now = null): string
    {
        $expiresAt = self::expiresAt($now);

        return $this->urls->generate(self::ROUTE, [
            'type' => $type->value,
            'id' => $id,
            'locale' => $locale,
            'expires' => $expiresAt,
            'signature' => $this->sign(self::SALT, $type, $id, $locale, $expiresAt),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function isValid(CatalogueItemType $type, int $id, string $locale, int $expiresAt, string $signature, ?\DateTimeImmutable $now = null): bool
    {
        return $this->verify(self::SALT, $type, $id, $locale, $expiresAt, $signature, $now);
    }

    /**
     * The page of the item showing its draft, or null when the item has no
     * page to show it on: no address, or offline, where the page is a 404.
     */
    public function pageUrlFor(CatalogueItem $item, ?\DateTimeImmutable $now = null): ?string
    {
        if (null === $item->url || !$item->online) {
            return null;
        }

        $expiresAt = self::expiresAt($now);
        $token = $expiresAt.'-'.$this->sign(self::PAGE_SALT, $item->type, $item->id, $item->locale, $expiresAt);

        return $item->url.(str_contains($item->url, '?') ? '&' : '?').http_build_query([self::PAGE_PARAMETER => $token]);
    }

    public function isValidPageToken(CatalogueItemType $type, int $id, string $locale, string $token, ?\DateTimeImmutable $now = null): bool
    {
        if (1 !== preg_match('/^(\d+)-([a-f0-9]{64})$/', $token, $parts)) {
            return false;
        }

        return $this->verify(self::PAGE_SALT, $type, $id, $locale, (int) $parts[1], $parts[2], $now);
    }

    private static function expiresAt(?\DateTimeImmutable $now): int
    {
        return ($now ?? new \DateTimeImmutable())->getTimestamp() + PreviewLink::LIFETIME_IN_SECONDS;
    }

    private function verify(string $salt, CatalogueItemType $type, int $id, string $locale, int $expiresAt, string $signature, ?\DateTimeImmutable $now): bool
    {
        if ($expiresAt < ($now ?? new \DateTimeImmutable())->getTimestamp()) {
            return false;
        }

        return hash_equals($this->sign($salt, $type, $id, $locale, $expiresAt), $signature);
    }

    private function sign(string $salt, CatalogueItemType $type, int $id, string $locale, int $expiresAt): string
    {
        return hash_hmac(
            'sha256',
            implode('|', [$type->value, $id, $locale, $expiresAt]),
            hash_hmac('sha256', $salt, $this->applicationSecret),
        );
    }
}
