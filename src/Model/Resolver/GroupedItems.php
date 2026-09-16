<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright © 2019 Scandiweb, Ltd (https://scandiweb.com)
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver;

use Magento\Catalog\Api\Data\ProductLinkInterface;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Deferred\Product;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GroupedProduct\Model\Product\Initialization\Helper\ProductLinks\Plugin\Grouped;
use Magento\GroupedProduct\Model\Product\Type\Grouped as GroupedAlias;
use Magento\GroupedProductGraphQl\Model\Resolver\GroupedItems as MagentoGroupedItems;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product as ProductDataProvider;
use ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;
use ScandiPWA\Performance\Model\Resolver\ResolveInfoFieldsTrait;

class GroupedItems extends MagentoGroupedItems
{
    use ResolveInfoFieldsTrait;

    /**
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param DataPostProcessor $postProcessor
     * @param ProductDataProvider $productDataProvider
     * @param Product $productResolver
     * @param GroupedAlias $grouped
     */
    public function __construct(
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly DataPostProcessor $postProcessor,
        private readonly ProductDataProvider $productDataProvider,
        Product $productResolver,
        private readonly GroupedAlias $grouped
    ) {
        parent::__construct($productResolver);
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

        $itemData = [];
        $productSKUs = [];
        $result = [];
        $productModel = $value['model'];

        // the associated-products cache is keyed per request, so a price query already filled it with partial data
        $this->grouped->flushAssociatedProductsCache($productModel);
        $links = $productModel->getProductLinks();

        foreach ($links as $link) {
            /** @var ProductLinkInterface $link */
            if ($link->getLinkType() !== Grouped::TYPE_NAME) {
                continue;
            }

            $productSKU = $link->getLinkedProductSku();

            $itemData[$productSKU] = [
                'position' => (int)$link->getPosition(),
                'qty' => $link->getExtensionAttributes()->getQty(),
                'sku' => $productSKU
            ];

            $productSKUs[] = $productSKU;
        }

        $attributeCodes = $this->getFieldsFromProductInfo($info, 'items/product');

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('sku', $productSKUs, 'in')
            ->create();

        $products = $this->productDataProvider
            ->getList(
                $searchCriteria,
                $attributeCodes,
                false,
                true
            )
            ->getItems();

        $productsData = $this->postProcessor->process(
            $products,
            'items/product',
            $info
        );

        foreach ($productsData as $productData) {
            $sku = $productData['sku'];

            if (!isset($itemData[$sku])) {
                continue;
            }

            $resultItem = $itemData[$sku];
            $resultItem['product'] = $productData;

            $result[] = $resultItem;
        }

        return $result;
    }
}
