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

namespace TheliaCMS\Catalogue;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\ProductQuery;
use Thelia\Model\RewritingUrlQuery;

/**
 * How an export names a catalogue item so that another site finds it: the
 * ids of two shops have nothing in common, their references and addresses
 * do. A product is found by its reference first; any item by the address it
 * answers at, in any language; a store, which has no rewritten address, by
 * the slug of its SEO record.
 */
final readonly class CatalogueItemKeys
{
    /**
     * @return array{reference?: string, slug?: string, urls: array<string, string>}
     */
    public function keyOf(CatalogueItemType $type, int $id): array
    {
        $key = ['urls' => []];

        if (CatalogueItemType::Dealer === $type) {
            $slug = DealerStores::slugOf($id);

            if (null !== $slug) {
                $key['slug'] = $slug;
            }

            return $key;
        }

        if (CatalogueItemType::Product === $type) {
            $reference = trim((string) ProductQuery::create()->findPk($id)?->getRef());

            if ('' !== $reference) {
                $key['reference'] = $reference;
            }
        }

        $urls = RewritingUrlQuery::create()
            ->filterByView($type->value)
            ->filterByViewId((string) $id)
            ->filterByRedirected(null, Criteria::ISNULL)
            ->orderByViewLocale()
            ->find();

        foreach ($urls as $url) {
            $key['urls'][(string) $url->getViewLocale()] = (string) $url->getUrl();
        }

        return $key;
    }

    /**
     * The id of the item the key names on this site, or null when none
     * matches.
     *
     * @param array<string, mixed> $key
     */
    public function find(CatalogueItemType $type, array $key): ?int
    {
        if (CatalogueItemType::Dealer === $type) {
            $slug = $key['slug'] ?? null;

            return \is_string($slug) && '' !== $slug ? DealerStores::idOfSlug($slug) : null;
        }

        $reference = $key['reference'] ?? null;

        if (CatalogueItemType::Product === $type && \is_string($reference) && '' !== $reference) {
            $product = ProductQuery::create()->filterByRef($reference)->findOne();

            if (null !== $product) {
                return (int) $product->getId();
            }
        }

        foreach (\is_array($key['urls'] ?? null) ? $key['urls'] : [] as $locale => $address) {
            if (!\is_string($address) || '' === $address) {
                continue;
            }

            $url = RewritingUrlQuery::create()
                ->filterByView($type->value)
                ->filterByViewLocale((string) $locale)
                ->filterByUrl($address)
                ->filterByRedirected(null, Criteria::ISNULL)
                ->findOne();

            if (null !== $url) {
                return (int) $url->getViewId();
            }
        }

        return null;
    }
}
