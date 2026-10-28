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

namespace TheliaCMS\Generation\Text;

use Psr\Cache\CacheItemPoolInterface;

/**
 * What a call for a text answered, kept a few minutes under the id the screen
 * gave it.
 *
 * The server writes the text to the end once asked, whether or not the
 * browser still waits: a connection cut on the way back loses an answer that
 * was written and billed. The screen asks again by the same id rather than
 * paying for a second text. An id is read back by the administrator who asked
 * only.
 */
final readonly class TextOutcomes
{
    /** How long an answer waits to be read back, in seconds. */
    private const int LIFETIME = 600;

    private const string ID_PATTERN = '/^[A-Za-z0-9-]{16,64}$/';

    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {
    }

    public static function isId(string $requestId): bool
    {
        return 1 === preg_match(self::ID_PATTERN, $requestId);
    }

    /**
     * @param array<string, string> $answer what the route answered, as JSON
     */
    public function keep(int $adminId, string $requestId, int $status, array $answer): void
    {
        if (!self::isId($requestId)) {
            return;
        }

        $item = $this->cache->getItem(self::key($adminId, $requestId));
        $item->set(['status' => $status, 'answer' => $answer]);
        $item->expiresAfter(self::LIFETIME);
        $this->cache->save($item);
    }

    /**
     * @return array{status: int, answer: array<string, string>}|null
     */
    public function find(int $adminId, string $requestId): ?array
    {
        if (!self::isId($requestId)) {
            return null;
        }

        $item = $this->cache->getItem(self::key($adminId, $requestId));
        $value = $item->isHit() ? $item->get() : null;

        return \is_array($value) && \is_int($value['status'] ?? null) && \is_array($value['answer'] ?? null) ? $value : null;
    }

    private static function key(int $adminId, string $requestId): string
    {
        return 'theliacms_text_outcome_'.hash('xxh3', $adminId.'|'.$requestId);
    }
}
