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

namespace TheliaCMS\Vitrine;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\RewritingUrl;
use Thelia\Model\RewritingUrlQuery;
use TheliaCMS\Page\PublishedPageRepository;
use TheliaCMS\TheliaCMS;

/**
 * Whether a rewritten address answers, asked without running the request.
 *
 * Only rewritten addresses are looked up. The paths declared as Symfony routes
 * already tolerate a trailing slash, so they never reach a caller of this class;
 * what 404s on the slash is exactly what the rewriting table holds.
 *
 * `rewriting_url.url` is a VARBINARY with a unique index, so this is one indexed
 * lookup on an exact byte string.
 */
final readonly class RewrittenAddressReachability
{
    public function __construct(
        private PublishedPageRepository $pages,
    ) {
    }

    /**
     * Whether the table holds that address exactly as it is written here.
     *
     * The trailing slash is part of the question: a takeover that recorded its
     * old addresses in both forms answers the 301 of the rename on either one,
     * and anything stepping in before that turns one hop into two.
     */
    public function isKnown(string $path): bool
    {
        return $this->rowFor($path) instanceof RewritingUrl;
    }

    /**
     * A row kept behind a rename counts: that address answers, with a 301 of its
     * own. Sending a visitor through two redirections is worth more than a 404.
     */
    public function answers(string $path): bool
    {
        $address = $this->rowFor($path);

        if (!$address instanceof RewritingUrl) {
            return false;
        }

        // Only the pages of this module are answered for. Whether a product, a
        // brand or a content of the shop is online is not knowable from here,
        // and the shop answers 404 on their addresses with a slash: redirecting
        // on the strength of the row alone sent visitors to addresses nobody
        // checked.
        if (TheliaCMS::PAGE_VIEW !== (string) $address->getView()) {
            return false;
        }

        // An address whose page is binned, offline or waiting for its
        // publication date answers 404 too, and redirecting to a 404 is worse
        // than the 404 asked for.
        return $this->pages->isReachable((int) $address->getViewId(), (string) $address->getViewLocale());
    }

    /**
     * Only the leading slash is dropped: the trailing one is what the callers
     * above are asking about, so trimming both ends would make the two questions
     * the same one.
     *
     * The path arrives as it was written on the wire, so an accented address is
     * still percent encoded, while the column holds the bytes it decodes to. The
     * core resolver decodes the same way before looking a row up.
     */
    private function rowFor(string $path): ?RewritingUrl
    {
        $url = urldecode(ltrim($path, '/'));

        if ('' === $url) {
            return null;
        }

        return RewritingUrlQuery::create()
            ->filterByUrl($url)
            ->orderById(Criteria::DESC)
            ->findOne();
    }
}
