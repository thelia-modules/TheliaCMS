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

/**
 * Signs the links that show a draft page to someone who is not logged in.
 *
 * A client reviewing a page before it goes online has no back-office account,
 * so the link itself carries the authorisation: a keyed hash over the page,
 * the language and an expiry date. Nothing is stored — the key is derived from
 * the application secret, so revoking every outstanding link is a matter of
 * rotating that secret.
 */
final readonly class PreviewLink
{
    /** How long a shared link stays valid. */
    public const int LIFETIME_IN_SECONDS = 72 * 3600;

    private const string ROUTE = 'cms.page.preview';
    private const string BLOCK_ROUTE = 'cms.block.preview';

    public function __construct(
        private UrlGeneratorInterface $urls,
        #[Autowire('%kernel.secret%')]
        private string $applicationSecret,
    ) {
    }

    public function urlFor(int $pageId, string $locale, ?\DateTimeImmutable $now = null): string
    {
        $expiresAt = ($now ?? new \DateTimeImmutable())->getTimestamp() + self::LIFETIME_IN_SECONDS;

        return $this->urls->generate(self::ROUTE, [
            'id' => $pageId,
            'locale' => $locale,
            'expires' => $expiresAt,
            'signature' => $this->sign($pageId, $locale, $expiresAt),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * The draft of a block, shown in the default page template: a block has
     * no address of its own, and its editor still has to see it in the theme.
     */
    public function urlForBlock(int $blockId, string $locale, ?\DateTimeImmutable $now = null): string
    {
        $expiresAt = ($now ?? new \DateTimeImmutable())->getTimestamp() + self::LIFETIME_IN_SECONDS;

        return $this->urls->generate(self::BLOCK_ROUTE, [
            'id' => $blockId,
            'locale' => $locale,
            'expires' => $expiresAt,
            'signature' => $this->sign($blockId, $locale, $expiresAt, 'block'),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function isValid(int $pageId, string $locale, int $expiresAt, string $signature, ?\DateTimeImmutable $now = null): bool
    {
        return $this->isValidFor('page', $pageId, $locale, $expiresAt, $signature, $now);
    }

    public function isValidForBlock(int $blockId, string $locale, int $expiresAt, string $signature, ?\DateTimeImmutable $now = null): bool
    {
        return $this->isValidFor('block', $blockId, $locale, $expiresAt, $signature, $now);
    }

    private function isValidFor(string $kind, int $id, string $locale, int $expiresAt, string $signature, ?\DateTimeImmutable $now): bool
    {
        if ($expiresAt < ($now ?? new \DateTimeImmutable())->getTimestamp()) {
            return false;
        }

        // Constant-time: a plain comparison leaks the signature one byte at a
        // time to anyone willing to measure.
        return hash_equals($this->sign($id, $locale, $expiresAt, $kind), $signature);
    }

    /**
     * A link signed for a page is no link to the block of the same id: the
     * kind is part of what is signed.
     */
    private function sign(int $id, string $locale, int $expiresAt, string $kind = 'page'): string
    {
        return hash_hmac(
            'sha256',
            implode('|', 'page' === $kind ? [$id, $locale, $expiresAt] : [$kind, $id, $locale, $expiresAt]),
            // Salted so this signature cannot be replayed anywhere else the
            // application secret is used.
            hash_hmac('sha256', 'thelia-cms.page-preview', $this->applicationSecret),
        );
    }
}
