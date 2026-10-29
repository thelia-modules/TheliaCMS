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

namespace TheliaCMS\Generation\Tool;

/**
 * What ReadUrlTool brought back: the text of a page, or the code of why it
 * could not be read. The code is one of a short list, never a message of the
 * network, so it can go back to the model and to the ledger as it is.
 */
final readonly class FetchedPage
{
    private function __construct(
        public string $url,
        public ?string $text,
        public ?string $errorCode,
    ) {
    }

    public static function read(string $url, string $text): self
    {
        return new self($url, $text, null);
    }

    public static function failed(string $url, string $errorCode): self
    {
        return new self($url, null, $errorCode);
    }

    public function wasRead(): bool
    {
        return null === $this->errorCode;
    }
}
