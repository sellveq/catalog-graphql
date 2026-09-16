<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright © 2021 Scandiweb, Ltd (https://scandiweb.com)
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Product;

use Magento\Catalog\Helper\Data as CatalogData;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option;
use Magento\Catalog\Model\Product\Option\Value as OptionValue;
use Magento\Catalog\Pricing\Price\CalculateCustomOptionCatalogRule;
use Magento\CatalogGraphQl\Model\Resolver\Product\Options as CoreOptions;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Pricing\PriceCurrencyInterface;

class Options extends CoreOptions
{
    protected const string OPTION_TYPE = 'custom-option';
    protected const string DYNAMIC_TYPE = 'DYNAMIC';

    /**
     * @var Uid
     */
    protected $uidEncoder;

    /**
     * @param PriceCurrencyInterface $priceCurrency
     * @param CatalogData $catalogData
     * @param CalculateCustomOptionCatalogRule $calculateCustomOptionCatalogRule
     * @param Uid|null $uidEncoder
     */
    public function __construct(
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly CatalogData $catalogData,
        private readonly CalculateCustomOptionCatalogRule $calculateCustomOptionCatalogRule,
        ?Uid $uidEncoder = null
    ) {
        $this->uidEncoder = $uidEncoder ?: ObjectManager::getInstance()
            ->get(Uid::class);
    }

    /**
     * @param mixed $price
     * @param mixed $isPercent
     * @param mixed $product
     * @return float
     */
    public function getPrice($price, $isPercent, $product)
    {
        $catalogPriceValue = $this->calculateCustomOptionCatalogRule->execute(
            $product,
            (float)$price,
            $isPercent
        );

        if ($catalogPriceValue !== null) {
            return $catalogPriceValue;
        }

        return $price;
    }

    /**
     * @param array $optionArray
     * @param mixed $optionValue
     * @param mixed $product
     * @param string $currentCurrency
     * @return void
     */
    public function updateOptionPriceData(array &$optionArray, $optionValue, $product, $currentCurrency)
    {
        $optionArray['price_type'] = $optionValue->getPriceType() !== null
            ? strtoupper($optionValue->getPriceType())
            : self::DYNAMIC_TYPE;
        $optionArray['price'] = $this->getPrice(
            $optionArray['price'],
            strtolower($optionValue->getPriceType()) == OptionValue::TYPE_PERCENT,
            $product
        );

        $selectionPrice = $optionArray['price'];
        $optionArray['currency'] = $currentCurrency;

        // a percentage option prices off the parent final price, a fixed one off its own value
        $taxablePrice = strtolower($optionValue->getPriceType()) == OptionValue::TYPE_PERCENT
            ? $product->getFinalPrice() * $selectionPrice / 100
            : $selectionPrice;
        $taxablePrice = $this->priceCurrency->convert($taxablePrice);

        $optionArray['priceInclTax'] = $this->catalogData->getTaxPrice(
            $product, $taxablePrice, true, null, null, null, null, null, null
        );
        $optionArray['priceExclTax'] = $this->catalogData->getTaxPrice(
            $product, $taxablePrice, false, null, null, null, null, null, null
        );
    }

    /**
     * format product's option data to conform to GraphQL schema
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

        /** @var Product $product */
        $product = $value['model'];
        $store = $context->getExtensionAttributes()->getStore();
        $currentCurrency = $store->getCurrentCurrencyCode();

        $options = null;
        if (!empty($product->getOptions())) {
            $options = [];
            /** @var Option $option */
            foreach ($product->getOptions() as $key => $option) {
                $options[$key] = $option->getData();
                $options[$key]['required'] = $option->getIsRequire();
                $options[$key]['product_sku'] = $option->getProductSku();
                $options[$key]['uid'] = $this->uidEncoder->encode(
                    self::OPTION_TYPE . '/' . $option->getOptionId()
                );

                $values = $option->getValues() ?: [];

                /** @var Option\Value $optionValue */
                foreach ($values as $valueKey => $optionValue) {
                    $options[$key]['value'][$valueKey] = $optionValue->getData();
                    $this->updateOptionPriceData(
                        $options[$key]['value'][$valueKey],
                        $optionValue,
                        $product,
                        $currentCurrency
                    );
                }

                if (empty($values)) {
                    $options[$key]['value'] = $option->getData();
                    $this->updateOptionPriceData($options[$key]['value'], $option, $product, $currentCurrency);
                }
            }
        }

        return $options;
    }
}
