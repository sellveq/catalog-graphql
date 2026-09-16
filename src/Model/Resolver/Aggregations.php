<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright © 2018 Scandiweb, Ltd (https://scandiweb.com)
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver;

use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\Builder\Aggregations\Category;
use Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\LayerBuilder;
use Magento\CatalogGraphQl\Model\Resolver\Aggregations as AggregationsBase;
use Magento\Directory\Model\PriceCurrency;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class Aggregations extends AggregationsBase
{
    /** Code of the price attribute in aggregations */
    public const string PRICE_ATTR_CODE = 'price';

    /** Code of the category id in aggregations */
    public const string CATEGORY_ID_CODE = 'category_id';

    /** ID of the top level menu items */
    public const int TOP_NAVIGATION_LEVEL_ID = 2;

    /**
     * {@inheritdoc}
     */
    public function __construct(
        LayerBuilder $layerBuilder,
        private readonly Attribute $attribute,
        private readonly CategoryRepository $categoryRepository,
        ?PriceCurrency $priceCurrency = null,
        ?Category\IncludeDirectChildrenOnly $includeDirectChildrenOnly = null
    ) {
        parent::__construct(
            $layerBuilder,
            $priceCurrency,
            $includeDirectChildrenOnly
        );
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
        $result = parent::resolve($field, $context, $info, $value, $args);

        $isSearch = isset($value['layer_type']) && $value['layer_type'] == 'search';

        $result = $this->processPriceFilter($result);
        $result = $this->enhanceAttributes($result, $isSearch);

        // on the search results page we should show only top level categories
        if ($isSearch) {
            $result = $this->removeNonTopLevelCategories($result);
        }

        return $result;
    }

    /**
     * process filters and set price filter last option value so it has no upper bound
     * @param array $result Filters
     * @return array
     */
    protected function processPriceFilter(array $result): array
    {
        return array_map(function ($item) {
            if ($item['attribute_code'] === self::PRICE_ATTR_CODE) {
                $lastIdx = count($item['options']) - 1;

                foreach ($item['options'] as $index => $option) {
                    if ($lastIdx != 0 && $index == $lastIdx) {
                        $item['options'][$index]['label'] =
                            preg_replace('/(\d+\.?\d*)~(\d+\.?\d*)/', '$1~*', $option['label']);
                        $item['options'][$index]['value'] =
                            preg_replace('/(\d+\.?\d*)_(\d+\.?\d*)/', '$1_*', $option['value']);
                    } else {
                        $item['options'][$index]['label'] = preg_replace_callback(
                            '/(\d+\.?\d*~)(\d+\.?\d*)/',
                            function ($matches) {
                                return $matches[1] . ((float)$matches[2] - 0.01);
                            },
                            $option['label']
                        );
                        $item['options'][$index]['value'] = preg_replace_callback(
                            '/(\d+\.?\d*_)(\d+\.?\d*)/',
                            function ($matches) {
                                return $matches[1] . ((float)$matches[2] - 0.01);
                            },
                            $option['value']
                        );
                    }
                }
            }

            return $item;
        }, $result);
    }

    /**
     * process options and replace '1' and '0' labels for options having boolean type.
     * @param array $result Filters
     * @param mixed $isSearch
     * @return array
     * @throws LocalizedException
     */
    protected function enhanceAttributes(array $result, $isSearch): array
    {
        foreach ($result as $attr => $attrGroup) {
            // category_id is not a catalog_product attribute, so it carries no filterable or position data
            if ($attrGroup['attribute_code'] == self::CATEGORY_ID_CODE) {
                $result[$attr]['is_boolean'] = false;
                $result[$attr]['position'] = 0;
                $result[$attr]['has_swatch'] = false;
                continue;
            }

            $attribute = $this->attribute->loadByCode('catalog_product', $attrGroup['attribute_code']);

            // an attribute not filterable in search is dropped from the search aggregations only
            if ($isSearch) {
                if (!$attribute->getIsFilterableInSearch()) {
                    unset($result[$attr]);
                    continue;
                }
            }

            // the storefront renders a boolean attribute as a toggle rather than an option list
            $result[$attr]['is_boolean'] = $attribute->getFrontendInput() === 'boolean';
            $result[$attr]['position'] = $attribute->getPosition();

            // swatch_input_type lives in additional_data, and a removed swatch leaves the option behind
            $additionalData = $attribute->getAdditionalData();
            if (is_null($additionalData)) {
                $result[$attr]['has_swatch'] = false;
            } else {
                $additionalDataParsed = json_decode($additionalData, true);
                $result[$attr]['has_swatch'] = isset($additionalDataParsed['swatch_input_type']);
            }
        }

        return $result;
    }

    /**
     * @param array $result
     * @return array
     * @throws NoSuchEntityException
     */
    protected function removeNonTopLevelCategories(array $result): array
    {
        foreach ($result as $attr => $attrGroup) {
            if ($attrGroup['attribute_code'] == self::CATEGORY_ID_CODE) {
                $newOptions = [];

                foreach ($attrGroup['options'] as $option) {
                    $category = $this->categoryRepository->get($option['value']);

                    if (!$category->getIsActive()) {
                        continue;
                    }

                    if ($category->getLevel() == self::TOP_NAVIGATION_LEVEL_ID) {
                        $newOptions[] = $option;
                    }
                }

                $result[$attr]['options'] = $newOptions;
            }
        }

        return $result;
    }
}
