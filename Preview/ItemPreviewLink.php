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
use TheliaCMS\Catalogue\CatalogueItemType;

/**
 * A shareable link to the draft of the document of a catalogue item, signed
 * like the link to the draft of a page (PreviewLink) under a salt of its own,
 * so neither signature opens the other.
 */
final readonly class ItemPreviewLink
{
    private const string ROUTE = 'cms.item.preview';

    public function __construct(
        private UrlGeneratorInterface $urls,
        #[Autowire('%kernel.secret%')]
        private string $applicationSecret,
    ) {
    }

    public function urlFor(CatalogueItemType $type, int $id, string $locale, ?\DateTimeImmutable $now = null): string
    {
        $expiresAt = ($now ?? new \DateTimeImmutable())->getTimestamp() + PreviewLink::LIFETIME_IN_SECONDS;

        return $this->urls->generate(self::ROUTE, [
            'type' => $type->value,
            'id' => $id,
            'locale' => $locale,
            'expires' => $expiresAt,
            'signature' => $this->sign($type, $id, $locale, $expiresAt),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function isValid(CatalogueItemType $type, int $id, string $locale, int $expiresAt, string $signature, ?\DateTimeImmutable $now = null): bool
    {
        if ($expiresAt < ($now ?? new \DateTimeImmutable())->getTimestamp()) {
            return false;
        }

        return hash_equals($this->sign($type, $id, $locale, $expiresAt), $signature);
    }

    private function sign(CatalogueItemType $type, int $id, string $locale, int $expiresAt): string
    {
        return hash_hmac(
            'sha256',
            implode('|', [$type->value, $id, $locale, $expiresAt]),
            hash_hmac('sha256', 'thelia-cms.item-preview', $this->applicationSecret),
        );
    }
}
