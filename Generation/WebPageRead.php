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

namespace TheliaCMS\Generation;

/**
 * One web page the model asked to read during a call: its address, the error
 * the API answered when it could not read it, and the tokens of what it read,
 * counted after the call. The count is that of the page alone: the model may
 * read it again at a later turn of the same call, from the cache or not.
 */
final readonly class WebPageRead
{
    public function __construct(
        public string $url,
        public ?string $errorCode = null,
        public ?int $tokens = null,
    ) {
    }

    public function wasRead(): bool
    {
        return null === $this->errorCode;
    }

    public function withTokens(?int $tokens): self
    {
        return new self($this->url, $this->errorCode, $tokens);
    }

    /**
     * @return array{url: string, error: string|null, tokens: int|null}
     */
    public function toArray(): array
    {
        return ['url' => $this->url, 'error' => $this->errorCode, 'tokens' => $this->tokens];
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            (string) ($row['url'] ?? ''),
            isset($row['error']) ? (string) $row['error'] : null,
            isset($row['tokens']) ? (int) $row['tokens'] : null,
        );
    }
}
