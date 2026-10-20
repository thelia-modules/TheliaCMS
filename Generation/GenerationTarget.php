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

use TheliaCMS\Model\CmsPage;

/**
 * What the generator writes for: a page — what to call it, its type and what
 * the editor said about it.
 */
final readonly class GenerationTarget
{
    public const string TYPE_PAGE = 'page';

    private function __construct(
        public string $type,
        public int $id,
        public string $title,
        public ?string $pageType,
        public ?string $summary,
    ) {
    }

    public static function page(CmsPage $page, string $locale): self
    {
        $page->setLocale($locale);
        $summary = trim(strip_tags((string) $page->getChapo()));

        return new self(
            self::TYPE_PAGE,
            (int) $page->getId(),
            trim((string) $page->getTitle()),
            (string) $page->getPageType(),
            '' === $summary ? null : $summary,
        );
    }
}
