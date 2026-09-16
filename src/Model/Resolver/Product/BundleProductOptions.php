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

use Magento\Bundle\Model\Option;
use Magento\Bundle\Model\Product\Price;
use Magento\Bundle\Model\Product\Type as Bundle;
use Magento\Bundle\Model\Selection;
use Magento\Catalog\Helper\Data as CatalogData;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\EnumLookup;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Pricing\PriceCurrencyInterface;

class BundleProductOptions implements ResolverInterface
{
    /**
     * @param EnumLookup $enumLookup
     * @param CatalogData $catalogData
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        private readonly EnumLookup $enumLookup,
        private readonly CatalogData $catalogData,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {}

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

        /** @var Product $bundleProduct */
        $bundleProduct = $value['model'];

        if ($bundleProduct->getTypeId() !== Bundle::TYPE_CODE) {
            return [];
        }

        /** @var Price $priceModel */
        $priceModel = $bundleProduct->getPriceModel();

        $result = [];

        /** @var Option $bundleOption */
        foreach ($priceModel->getOptions($bundleProduct) as $bundleOption) {
            $selectionsResult = [];

            /** @var Selection $optionSelection */
            foreach (($bundleOption->getSelections() ?? []) as $optionSelection) {
                // a fixed-price bundle is taxed on the bundle, a dynamic one on the referenced product
                $taxableItem = $bundleProduct->getPriceType() == Price::PRICE_TYPE_FIXED
                    ? $bundleProduct
                    : $optionSelection;

                $selectionPrice = $priceModel->getSelectionPrice($bundleProduct, $optionSelection, 1);
                $selectionPriceInclTax = $this->catalogData->getTaxPrice(
                    $taxableItem, $selectionPrice, true, null, null, null, null, null, false
                );
                $selectionPriceExclTax = $this->catalogData->getTaxPrice(
                    $taxableItem, $selectionPrice, false, null, null, null, null, null, false
                );

                $selectionPriceType = $this->enumLookup->getEnumValueFromField(
                    'PriceTypeEnum',
                    (string)$optionSelection->getSelectionPriceType()
                ) ?: 'DYNAMIC';

                $regularPrice = $bundleProduct->getPriceType() == Price::PRICE_TYPE_FIXED
                    ? $selectionPriceType == 'PERCENT'
                    ? ($bundleProduct->getPrice() * ($optionSelection->getSelectionPriceValue() / 100))
                    : $optionSelection->getSelectionPriceValue()
                    : $optionSelection->getPrice();

                $regularPriceInclTax = $this->catalogData->getTaxPrice($taxableItem, $regularPrice, true);
                $regularPriceExclTax = $this->catalogData->getTaxPrice($taxableItem, $regularPrice, false);

                $selectionsResult[] = [
                    'selection_id' => $optionSelection->getSelectionId(),
                    'name' => $optionSelection->getName(),
                    'regular_option_price' => $this->priceCurrency->convert($regularPriceInclTax),
                    'regular_option_price_excl_tax' => $this->priceCurrency->convert($regularPriceExclTax),
                    'final_option_price' => $this->priceCurrency->convert($selectionPriceInclTax),
                    'final_option_price_excl_tax' => $this->priceCurrency->convert($selectionPriceExclTax)
                ];
            }

            $result[] = [
                'option_id' => $bundleOption->getId(),
                'selection_details' => $selectionsResult
            ];
        }

        return $result;
    }
}
