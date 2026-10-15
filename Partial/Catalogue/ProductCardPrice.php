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

namespace TheliaCMS\Partial\Catalogue;

use Thelia\Domain\Taxation\TaxEngine\Calculator;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * The price the module's own product card shows: the default sale element, in
 * the default currency, taxed for the default country.
 *
 * Only the fallback card reads it. What a given visitor pays depends on their
 * country and their account, which is the theme's business: a theme that
 * sells draws the card itself.
 */
final readonly class ProductCardPrice
{
    /**
     * @return array{price: string, original_price: string|null}|null null when the product has nothing to sell
     */
    public function of(Product $product, string $locale): ?array
    {
        $saleElement = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne();

        if (!$saleElement instanceof ProductSaleElements) {
            return null;
        }

        // A shop without a default country, or a sale element without a price
        // in the default currency, still gets its card: without a price.
        try {
            $country = Country::getDefaultCountry();
            $currency = Currency::getDefaultCurrency();
            $prices = $saleElement->getPricesByCurrency($currency);
        } catch (\LogicException|\RuntimeException) {
            return null;
        }

        $calculator = (new Calculator())->load($product, $country);
        $price = (float) $calculator->getTaxedPrice($prices->getPrice());
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        $format = static fn (float $amount): string => (string) $formatter->formatCurrency($amount, (string) $currency->getCode());

        if ($saleElement->getPromo()) {
            return [
                'price' => $format((float) $calculator->getTaxedPrice($prices->getPromoPrice())),
                'original_price' => $format($price),
            ];
        }

        return ['price' => $format($price), 'original_price' => null];
    }
}
