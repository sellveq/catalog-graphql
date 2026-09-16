<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright © Magento, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Product;

use Magento\Catalog\Helper\Data as TaxHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\CatalogGraphQl\Model\PriceRangeDataProvider;
use Magento\CatalogGraphQl\Model\Resolver\Product\Price\ProviderPool as PriceProviderPool;
use Magento\CatalogGraphQl\Model\Resolver\Product\PriceRange as CorePriceRange;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Pricing\SaleableInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

class PriceRange extends CorePriceRange
{
    public const string XML_PRICE_INCLUDES_TAX = 'tax/calculation/price_includes_tax';
    public const string FINAL_PRICE = 'final_price';

    /**
     * @var float
     */
    protected $zeroThreshold = 0.0001;

    /**
     * @param PriceProviderPool $priceProviderPool
     * @param ScopeConfigInterface $scopeConfig
     * @param TaxHelper $taxHelper
     * @param PriceRangeDataProvider $priceRangeDataProvider
     */
    public function __construct(
        private readonly PriceProviderPool $priceProviderPool,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly TaxHelper $taxHelper,
        PriceRangeDataProvider $priceRangeDataProvider
    ) {
        parent::__construct($priceRangeDataProvider);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        /** @var StoreInterface $store */
        $store = $context->getExtensionAttributes()->getStore();

        /** @var Product $product */
        $product = $value['model'];

        $requestedFields = $info->getFieldSelection(10);
        $returnArray = [];

        if (isset($requestedFields['minimum_price'])) {
            $returnArray['minimum_price'] =  $this->getMinimumProductPrice($product, $store);
        }
        if (isset($requestedFields['maximum_price'])) {
            $returnArray['maximum_price'] =  $this->getMaximumProductPrice($product, $store);
        }
        return $returnArray;
    }

    /**
     * get formatted minimum product price
     * @param SaleableInterface $product
     * @param StoreInterface $store
     * @return array
     */
    protected function getMinimumProductPrice(SaleableInterface $product, StoreInterface $store): array
    {
        $priceProvider = $this->priceProviderPool->getProviderByProductType($product->getTypeId());

        $regularPrice = (float)$priceProvider->getMinimalRegularPrice($product)->getValue();
        $finalPrice = 0;

        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            $finalPrice = $product->getPriceInfo()->getPrice(self::FINAL_PRICE)->getValue();
        } else {
            $finalPrice = (float)$priceProvider->getMinimalFinalPrice($product)->getValue();
        }

        $discount = $this->calculateDiscount($product, $regularPrice, $finalPrice);

        $regularPriceExclTax = (float)$priceProvider->getMinimalRegularPrice($product)->getBaseAmount();
        $finalPriceExclTax = (float)$priceProvider->getMinimalFinalPrice($product)->getBaseAmount();

        if ($product->getTypeId() == ProductType::TYPE_SIMPLE) {
            $priceInfo = $product->getPriceInfo();
            $defaultRegularPrice = $priceInfo->getPrice(RegularPrice::PRICE_CODE)->getAmount()->getValue();
            $defaultFinalPrice = $priceInfo->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getValue();
            $defaultFinalPriceExclTax = $priceInfo->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getBaseAmount();

            $discount = $this->calculateDiscount($product, $defaultRegularPrice, $defaultFinalPrice);
        } else {
            $defaultRegularPrice = $this->taxHelper->getTaxPrice(
                $product,
                $product->getPrice(),
                $this->isPriceIncludesTax()
            );
            $defaultFinalPrice = (float)round($priceProvider->getRegularPrice($product)->getValue(), 2);
            $defaultFinalPriceExclTax = (float)$priceProvider->getRegularPrice($product)->getBaseAmount();
        }

        $minPriceArray = $this->formatPrice(
            $regularPrice,
            $regularPriceExclTax,
            $finalPrice,
            $finalPriceExclTax,
            $defaultRegularPrice,
            $defaultFinalPrice,
            $defaultFinalPriceExclTax,
            $discount,
            $store
        );
        $minPriceArray['model'] = $product;
        return $minPriceArray;
    }

    /**
     * get formatted maximum product price
     * @param SaleableInterface $product
     * @param StoreInterface $store
     * @return array
     */
    protected function getMaximumProductPrice(SaleableInterface $product, StoreInterface $store): array
    {
        $priceProvider = $this->priceProviderPool->getProviderByProductType($product->getTypeId());

        $regularPrice = (float)$priceProvider->getMaximalRegularPrice($product)->getValue();
        $finalPrice = (float)$priceProvider->getMaximalFinalPrice($product)->getValue();

        $discount = $this->calculateDiscount($product, $regularPrice, $finalPrice);

        $regularPriceExclTax = (float)$priceProvider->getMaximalRegularPrice($product)->getBaseAmount();
        $finalPriceExclTax = (float)$priceProvider->getMaximalFinalPrice($product)->getBaseAmount();

        if ($product->getTypeId() == ProductType::TYPE_SIMPLE) {
            $priceInfo = $product->getPriceInfo();
            $defaultRegularPrice = $priceInfo->getPrice(RegularPrice::PRICE_CODE)->getAmount()->getValue();
            $defaultFinalPrice = $priceInfo->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getValue();
            $defaultFinalPriceExclTax = $priceInfo->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getBaseAmount();

            $discount = $this->calculateDiscount($product, $defaultRegularPrice, $defaultFinalPrice);
        } else {
            $defaultRegularPrice = $this->taxHelper->getTaxPrice(
                $product,
                $product->getPrice(),
                $this->isPriceIncludesTax()
            );
            $defaultFinalPrice = (float)round($priceProvider->getRegularPrice($product)->getValue(), 2);
            $defaultFinalPriceExclTax = (float)$priceProvider->getRegularPrice($product)->getBaseAmount();
        }

        $maxPriceArray = $this->formatPrice(
            $regularPrice,
            $regularPriceExclTax,
            $finalPrice,
            $finalPriceExclTax,
            $defaultRegularPrice,
            $defaultFinalPrice,
            $defaultFinalPriceExclTax,
            $discount,
            $store
        );
        $maxPriceArray['model'] = $product;
        return $maxPriceArray;
    }

    /**
     * format price for GraphQl output
     * @param float $regularPrice
     * @param float $regularPriceExclTax
     * @param float $finalPrice
     * @param float $finalPriceExclTax
     * @param float $defaultRegularPrice
     * @param float $defaultFinalPrice
     * @param float $defaultFinalPriceExclTax
     * @param array $discount
     * @param StoreInterface $store
     * @return array
     */
    protected function formatPrice(
        float $regularPrice,
        float $regularPriceExclTax,
        float $finalPrice,
        float $finalPriceExclTax,
        float $defaultRegularPrice,
        float $defaultFinalPrice,
        float $defaultFinalPriceExclTax,
        array $discount,
        StoreInterface $store
    ): array {
        return [
            'regular_price' => [
                'value' => $regularPrice,
                'currency' => $store->getCurrentCurrencyCode()
            ],
            'regular_price_excl_tax' => [
                'value' => $regularPriceExclTax,
                'currency' => $store->getCurrentCurrencyCode()
            ],
            'final_price' => [
                'value' => $finalPrice,
                'currency' => $store->getCurrentCurrencyCode()
            ],
            'final_price_excl_tax' => [
                'value' => $finalPriceExclTax,
                'currency' => $store->getCurrentCurrencyCode()
            ],
            'default_price' => [
                'value' => $defaultRegularPrice,
                'currency' => $store->getCurrentCurrencyCode()
            ],
            'default_final_price' => [
                'value' => $defaultFinalPrice,
                'currency' => $store->getCurrentCurrencyCode()
            ],
            'default_final_price_excl_tax' => [
                'value' => $defaultFinalPriceExclTax,
                'currency' => $store->getCurrentCurrencyCode()
            ],
            'discount' => $discount,
        ];
    }

    /**
     * calculate the discount from the special price percentage, which bundle items need
     * @param Product $product
     * @param float $regularPrice
     * @param float $finalPrice
     * @return array
     */
    protected function calculateDiscount(Product $product, float $regularPrice, float $finalPrice): array
    {
        if ($product->getTypeId() !== 'bundle') {
            // the storefront recomputes the money value from percent_off, so rounding here shifts it by a cent
            $priceDifference = $regularPrice - $finalPrice;

            return [
                'amount_off' => $this->getPriceDifferenceAsValue($regularPrice, $finalPrice),
                'percent_off' => $this->getPriceDifferenceAsPercent($regularPrice, $finalPrice)
            ];
        }

        // a bundle stores its special price as a percentage, every other type as an amount
        $specialPricePrecentage = $this->getSpecialProductPrice($product);
        $percentOff = is_null($specialPricePrecentage) ? 0 : 100 - $specialPricePrecentage;

        return [
            'amount_off' => $regularPrice * ($percentOff / 100),
            'percent_off' => $percentOff
        ];
    }

    /**
     * get value difference between two prices
     * @param float $regularPrice
     * @param float $finalPrice
     * @return float
     */
    protected function getPriceDifferenceAsValue(float $regularPrice, float $finalPrice)
    {
        $difference = $regularPrice - $finalPrice;
        if ($difference <= $this->zeroThreshold) {
            return 0;
        }

        return round($difference, 2);
    }

    /**
     * get percent difference between two prices
     * @param float $regularPrice
     * @param float $finalPrice
     * @return float
     */
    protected function getPriceDifferenceAsPercent(float $regularPrice, float $finalPrice)
    {
        $difference = $this->getPriceDifferenceAsValue($regularPrice, $finalPrice);

        if ($difference <= $this->zeroThreshold || $regularPrice <= $this->zeroThreshold) {
            return 0;
        }

        return round(($difference / $regularPrice) * 100, 8);
    }

    /**
     * gets [active] special price value
     * @param Product $product
     * @return float|null
     */
    protected function getSpecialProductPrice(Product $product): ?float
    {
        $specialPrice = $product->getSpecialPrice();
        if (!$specialPrice) {
            return null;
        }

        $from = strtotime($product->getSpecialFromDate());
        $to = $product->getSpecialToDate() === null ? null : strtotime($product->getSpecialToDate());
        $now = time();

        return ($now >= $from && $now <= $to) || ($now >= $from && is_null($to)) ? (float)$specialPrice : null;
    }

    /**
     * @return mixed
     */
    protected function isPriceIncludesTax()
    {
        return $this->scopeConfig->getValue(
            self::XML_PRICE_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORES
        );
    }
}
