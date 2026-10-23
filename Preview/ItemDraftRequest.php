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

use Symfony\Component\HttpFoundation\RequestStack;
use TheliaCMS\Catalogue\CatalogueItemType;

/**
 * Whether the page being served was opened from the signed link to the
 * draft of one of its items (ItemPreviewLink::pageUrlFor()). Only the item,
 * the language and the time the link was signed for open the draft; any
 * other token leaves the page as visitors see it.
 *
 * A page that showed a draft is marked on its request, so ItemDraftResponse
 * keeps the answer out of caches and search indexes.
 */
final readonly class ItemDraftRequest
{
    public const string SERVED_ATTRIBUTE = '_cms_item_draft_served';

    public function __construct(
        private RequestStack $requests,
        private ItemPreviewLink $links,
    ) {
    }

    public function opensDraftOf(CatalogueItemType $type, int $id, string $locale): bool
    {
        $request = $this->requests->getMainRequest();
        $token = $request?->query->get(ItemPreviewLink::PAGE_PARAMETER);

        if (null === $request || !\is_string($token) || !$this->links->isValidPageToken($type, $id, $locale, $token)) {
            return false;
        }

        $request->attributes->set(self::SERVED_ATTRIBUTE, true);

        return true;
    }
}
