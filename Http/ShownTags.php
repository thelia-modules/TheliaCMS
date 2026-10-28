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

namespace TheliaCMS\Http;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * What the page being served shows besides itself, named as the tags a shared
 * cache files it under: a list of pages, a form.
 *
 * Read while the page is rendered, by whatever draws that part of it, because
 * nothing else knows: a list placed in the content of a page, or called from
 * the template of its type, changes with pages the page itself never names.
 */
final readonly class ShownTags
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    public function add(string ...$tags): void
    {
        $request = $this->requests->getMainRequest();

        if (null === $request) {
            return;
        }

        $shown = $request->attributes->get(PageCacheListener::SHOWN_TAGS_ATTRIBUTE);
        $shown = \is_array($shown) ? $shown : [];

        $request->attributes->set(PageCacheListener::SHOWN_TAGS_ATTRIBUTE, array_values(array_unique([...$shown, ...$tags])));
    }
}
