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

namespace TheliaCMS\Media\Admin;

use Symfony\Component\HttpFoundation\Request;
use TheliaCMS\Media\MediaFilter;
use TheliaCMS\Media\MediaOrder;

/**
 * What the list of the media asks for, read from its address so a filtered
 * list can be bookmarked, shared and come back to from an image.
 */
final readonly class MediaQuery
{
    public const int PER_PAGE = 48;

    public function __construct(
        public ?string $search = null,
        public ?MediaFilter $filter = null,
        public ?string $tag = null,
        public MediaOrder $order = MediaOrder::Newest,
        public int $page = 1,
    ) {
    }

    /**
     * A value the screen does not offer is read as no value: an address
     * edited by hand lists the library instead of failing.
     */
    public static function fromRequest(Request $request): self
    {
        $search = trim($request->query->getString('search'));
        $tag = trim($request->query->getString('tag'));

        return new self(
            search: '' !== $search ? $search : null,
            filter: MediaFilter::tryFrom($request->query->getString('filter')),
            tag: '' !== $tag ? $tag : null,
            order: MediaOrder::tryFrom($request->query->getString('order')) ?? MediaOrder::Newest,
            page: max(1, $request->query->getInt('page', 1)),
        );
    }

    public function isNarrowed(): bool
    {
        return null !== $this->search || null !== $this->filter || null !== $this->tag;
    }

    /**
     * The parameters of the address of this list, the page aside, as links
     * to another page of it keep them.
     *
     * @return array<string, string>
     */
    public function parameters(): array
    {
        return array_filter([
            'search' => $this->search,
            'filter' => $this->filter?->value,
            'tag' => $this->tag,
            'order' => MediaOrder::Newest === $this->order ? null : $this->order->value,
        ], static fn (?string $value): bool => null !== $value);
    }
}
