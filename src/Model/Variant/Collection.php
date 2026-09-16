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

namespace ScandiPWA\CatalogGraphQl\Model\Variant;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessorInterface;
use Magento\CatalogInventory\Helper\Stock as StockFilter;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CriteriaCheck;
use ScandiPWA\Performance\Model\Resolver\Products\CollectionPostProcessor;
use ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;

class Collection
{
    /**
     * @var Product[]
     */
    protected $parentProducts = [];

    /**
     * @var array
     */
    protected $childrenMap = [];

    /**
     * @var string[]
     */
    protected $attributeCodes = [];

    /**
     * @var SearchCriteria
     */
    protected $searchCriteria;

    /**
     * @param ProductCollectionFactory $collectionFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param MetadataPool $metadataPool
     * @param CollectionProcessorInterface $collectionProcessor
     * @param CollectionPostProcessor $collectionPostProcessor
     * @param DataPostProcessor $dataPostProcessor
     * @param DataPostProcessor\Stocks $stocksPostProcessor
     * @param ResourceConnection $connection
     * @param StockFilter $stockFilter
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ProductCollectionFactory $collectionFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly MetadataPool $metadataPool,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly CollectionPostProcessor $collectionPostProcessor,
        private readonly DataPostProcessor $dataPostProcessor,
        private readonly DataPostProcessor\Stocks $stocksPostProcessor,
        private readonly ResourceConnection $connection,
        private readonly StockFilter $stockFilter,
        private readonly StoreManagerInterface $storeManager
    ) {
        $this->searchCriteria = $this->searchCriteriaBuilder->create();
    }

    /**
     * add parent to collection filter
     * @param Product $product
     * @return void
     * @throws Exception
     */
    public function addParentProduct(Product $product): void
    {
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        $productId = $product->getData($linkField);

        if (isset($this->parentProducts[$productId])) {
            return;
        }

        if (!empty($this->childrenMap)) {
            $this->childrenMap = [];
        }

        $this->parentProducts[$productId] = $product;
    }

    /**
     * add attributes to collection filter
     * @param array $attributeCodes
     * @return void
     */
    public function addEavAttributes(array $attributeCodes): void
    {
        $this->attributeCodes = array_replace($this->attributeCodes, $attributeCodes);
    }

    /**
     * retrieve child products from for passed in parent id.
     * @param int $id
     * @param mixed $info
     * @return array
     */
    public function getChildProductsByParentId(int $id, $info): array
    {
        $childrenMap = $this->fetch($info);

        if (!isset($childrenMap[$id])) {
            return [];
        }

        return $childrenMap[$id];
    }

    /**
     * retrieve child products from for passed in parent id.
     * @param int $id
     * @param mixed $info
     * @return array
     * @throws LocalizedException
     */
    public function getChildProductsByParentIdPlp(int $id, $info): array
    {
        $childrenMap = $this->fetchPlp($info);

        if (!isset($childrenMap[$id])) {
            return [];
        }

        return $childrenMap[$id];
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return void
     */
    public function setSearchCriteria(SearchCriteriaInterface $searchCriteria)
    {
        $this->searchCriteria = $searchCriteria;
    }

    /**
     * get if we should return only one product, or we need to process them all
     * @param bool $includeFilters
     * @return bool
     */
    protected function getIsReturnSingleChild($includeFilters = false)
    {
        $isSingleProduct = CriteriaCheck::isSingleProductFilter($this->searchCriteria);

        if ($isSingleProduct) {
            return false;
        }

        $filters = $this->searchCriteria->getFilterGroups();

        if (count($filters) <= 0) {
            return false;
        }

        foreach ($this->searchCriteria->getFilterGroups() as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                switch ($filter->getField()) {
                    // a single-product request keeps the listing filters out of the variant criteria
                    case 'category_url_path':
                    case 'category_id':
                    case 'price':
                        if ($includeFilters) {
                            return true;
                        }

                        break;
                    default:
                        if (!$includeFilters) {
                            return false;
                        }
                        break;
                }
            }
        }

        if ($includeFilters) {
            return false;
        }

        return true;
    }

    /**
     * get list of child and map of them to parent products
     * @return array
     */
    protected function getChildCollectionMapAndList(): array
    {
        $childCollectionMap = [];
        $childProductsList = [];

        $parentIds = array_map(function ($product) {
            return $product->getId();
        }, $this->parentProducts);

        $conn = $this->connection->getConnection();
        $select = $conn->select()
            ->from(
                ['s' => $this->connection->getTableName('catalog_product_super_link')],
                ['product_id', 'parent_id']
            )
            ->where('s.parent_id IN (?)', $parentIds);

        $childrenPairs = $conn->fetchAll($select);

        foreach ($childrenPairs as $childrenPair) {
            $childId = $childrenPair['product_id'];
            $parentId = $childrenPair['parent_id'];

            if (!isset($childCollectionMap[$parentId])) {
                $childCollectionMap[$parentId] = [];
            }

            $childCollectionMap[$parentId][] = $childId;
            $childProductsList[] = $childId;
        }

        return [
            $childProductsList,
            $childCollectionMap
        ];
    }

    /**
     * @param int[] $childrenIds
     * @param bool $isSingleProduct
     * @return SearchCriteriaInterface
     */
    protected function getSearchCriteria(array $childrenIds, bool $isSingleProduct): SearchCriteriaInterface
    {
        if ($isSingleProduct) {
            $filterGroups = $this->searchCriteria->getFilterGroups();

            $skip = [
                'category_url_path',
                'category_id',
                'price',
                'customer_group_id'
            ];

            foreach ($filterGroups as $filterGroup) {
                $filters = $filterGroup->getFilters();

                foreach ($filters as $filter) {
                    $field = $filter->getField();

                    if (in_array($field, $skip)) {
                        continue;
                    }

                    $this->searchCriteriaBuilder->addFilter(
                        $filter->getField(),
                        $filter->getValue(),
                        $filter->getConditionType()
                    );
                }
            }
        }

        return $this->searchCriteriaBuilder
            ->addFilter('entity_id', $childrenIds, 'in')
            ->create();
    }

    /**
     * fetch all children products from parent id's.
     * @param mixed $info
     * @return array
     */
    protected function fetch($info): array
    {
        if (empty($this->parentProducts) || !empty($this->childrenMap)) {
            return $this->childrenMap;
        }

        [
            $childProductsList,
            $childCollectionMap
        ] = $this->getChildCollectionMapAndList();

        $collection = $this->collectionFactory->create();
        $isReturnSingleChild = $this->getIsReturnSingleChild(true);
        $searchCriteria = $this->getSearchCriteria($childProductsList, $isReturnSingleChild);

        $attributeData = $this->attributeCodes;

        if ($isReturnSingleChild) {
            $this->stockFilter->addInStockFilterToCollection($collection);
        }

        $this->collectionProcessor->process(
            $collection,
            $searchCriteria,
            $attributeData
        );

        $collection->load();

        $this->collectionPostProcessor->process(
            $collection,
            $attributeData
        );

        $products = $collection->getItems();
        $productsToProcess = [];

        if ($isReturnSingleChild) {
            foreach ($this->parentProducts as $parentProduct) {
                $parentId = $parentProduct->getId();
                $childIds = $childCollectionMap[$parentId];

                foreach ($products as $childProduct) {
                    if (in_array($childProduct->getId(), $childIds, true)) {
                        $productsToProcess[] = $childProduct;
                        break;
                    }
                }
            }
        } else {
            $productsToProcess = $products;
        }

        $productsData = $this->dataPostProcessor->process(
            $productsToProcess,
            'variants/product',
            $info,
            ['isSingleProduct' => CriteriaCheck::isSingleProductFilter($this->searchCriteria)]
        );

        foreach ($this->parentProducts as $product) {
            $parentId = $product->getId();
            $childIds = $childCollectionMap[$parentId];

            $this->childrenMap[$parentId] = [];

            foreach ($childIds as $childId) {
                if (!isset($productsData[$childId])) {
                    continue;
                }

                $productData = $productsData[$childId];

                $formattedChild = [
                    'product' => $productData,
                    'sku' => $productData['sku']
                ];

                $this->childrenMap[$parentId][] = $formattedChild;
            }
        }

        return $this->childrenMap;
    }

    /**
     * fetch all children products from parent id's, optimized for PLP page
     * @param mixed $info
     * @return array
     * @throws LocalizedException
     */
    protected function fetchPlp($info): array
    {
        if (empty($this->parentProducts) || !empty($this->childrenMap)) {
            return $this->childrenMap;
        }

        [
            $childProductsList,
            $childCollectionMap
        ] = $this->getChildCollectionMapAndList();

        $collection = $this->collectionFactory->create();
        $searchCriteria = $this->getSearchCriteria($childProductsList, false);

        $attributeData = $this->attributeCodes;

        $this->collectionProcessor->process(
            $collection,
            $searchCriteria,
            $attributeData
        );

        $collection->load();

        $this->collectionPostProcessor->process(
            $collection,
            $attributeData
        );

        $products = $collection->getItems();

        // the same post processor as the non-PLP variants, so stock resolves identically on both paths
        $stockStatusCallback = $this->stocksPostProcessor->process(
            $products,
            'variants_plp/product',
            $info,
            ['isSingleProduct' => false]
        );

        $productsData = [];
        $productAttributes = [];

        /** @var Product $product */
        foreach ($products as $product) {
            if (
                $product->isDisabled()
                || !in_array($this->storeManager->getWebsite()->getId(), $product->getWebsiteIds())
            ) {
                continue;
            }

            $productId = $product->getId();

            $productAttributes[$productId] = [];

            /** @var Attribute $attribute */
            foreach ($product->getAttributes() as $attributeCode => $attribute) {
                // only listing attributes reach the PLP payload, so the rest are dropped before serialising
                if (!$attribute->getUsedInProductListing()) {
                    continue;
                }

                $productAttributes[$productId][$attributeCode] = [
                    'attribute_code' => $attribute->getAttributeCode(),
                    'attribute_type' => $attribute->getFrontendInput(),
                    'attribute_label' => $attribute->getStoreLabel(),
                    'attribute_id' => $attribute->getAttributeId(),
                    'attribute_value' => $product->getData($attributeCode)
                ];
            }

            $stockStatusCallback($product);

            $productsData[$product->getId()] = $product->getData() + [
                'model' => $product,
                's_attributes' => $productAttributes[$productId]
            ];
        }

        foreach ($this->parentProducts as $product) {
            $parentId = $product->getId();
            $childIds = $childCollectionMap[$parentId];

            $this->childrenMap[$parentId] = [];

            foreach ($childIds as $childId) {
                if (!isset($productsData[$childId])) {
                    continue;
                }

                $productData = $productsData[$childId];

                $formattedChild = [
                    'product' => $productData,
                    'sku' => $productData['sku']
                ];

                $this->childrenMap[$parentId][] = $formattedChild;
            }
        }

        return $this->childrenMap;
    }
}
