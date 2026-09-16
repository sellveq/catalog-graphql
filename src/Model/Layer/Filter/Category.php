<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright © Scandiweb, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Layer\Filter;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\CatalogGraphQl\DataProvider\Category\Query\CategoryAttributeQuery;
use Magento\CatalogGraphQl\DataProvider\CategoryAttributesMapper;
use Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\Builder\Aggregations\Category\IncludeDirectChildrenOnly;
use Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\Builder\Category as OriginalCategoryBuilder;
use Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\Formatter\LayerFormatter;
use Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\RootCategoryProvider;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\GraphQl\Query\Uid;
use ScandiPWA\CatalogGraphQl\Model\Layer\AttributeDataProvider;

class Category extends OriginalCategoryBuilder
{
    private static string $CATEGORY_ATTRIBUTE_CODE = 'category_ids';

    /**
     * @param CategoryAttributeQuery $categoryAttributeQuery
     * @param CategoryAttributesMapper $attributesMapper
     * @param RootCategoryProvider $rootCategoryProvider
     * @param ResourceConnection $resourceConnection
     * @param LayerFormatter $layerFormatter
     * @param IncludeDirectChildrenOnly $includeDirectChildrenOnly
     * @param CollectionFactory $categoryCollectionFactory
     * @param Uid $uidEncoder
     * @param AttributeDataProvider $attributeDataProvider
     */
    public function __construct(
        CategoryAttributeQuery $categoryAttributeQuery,
        CategoryAttributesMapper $attributesMapper,
        RootCategoryProvider $rootCategoryProvider,
        ResourceConnection $resourceConnection,
        LayerFormatter $layerFormatter,
        IncludeDirectChildrenOnly $includeDirectChildrenOnly,
        CollectionFactory $categoryCollectionFactory,
        Uid $uidEncoder,
        private readonly AttributeDataProvider $attributeDataProvider
    ) {
        parent::__construct(
            $categoryAttributeQuery,
            $attributesMapper,
            $rootCategoryProvider,
            $resourceConnection,
            $layerFormatter,
            $includeDirectChildrenOnly,
            $categoryCollectionFactory,
            $uidEncoder
        );
    }

    /**
     * {@inheritdoc}
     */
    public function build(AggregationInterface $aggregation, ?int $storeId): array
    {
        $result = parent::build($aggregation, $storeId);

        // core hard-codes the English bucket label, so the store-scoped attribute label replaces it
        if (count($result) > 0) {
            $attributeData = $this->attributeDataProvider->getAttributeData(self::$CATEGORY_ATTRIBUTE_CODE, $storeId);
            $attributeLabel = $attributeData['attribute_store_label'] ?? $attributeData['frontend_label'];

            $result[0]['label'] = $attributeLabel;
        }

        return $result;
    }
}
