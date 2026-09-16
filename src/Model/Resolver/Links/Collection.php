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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Links;

use Magento\Bundle\Model\ResourceModel\Selection\CollectionFactory;
use Magento\Bundle\Model\Selection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\GraphQl\Query\EnumLookup;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use ScandiPWA\Performance\Model\Resolver\Products\CollectionPostProcessor;
use ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;
use ScandiPWA\Performance\Model\Resolver\ResolveInfoFieldsTrait;
use Zend_Db_Select_Exception;

class Collection
{
    use ResolveInfoFieldsTrait;

    /**
     * @var int[]
     */
    protected $optionIds = [];

    /**
     * @var int[]
     */
    protected $parentIds = [];

    /**
     * @var array
     */
    protected $links = [];

    /**
     * @var ResolveInfo|null
     */
    protected $resolveInfo;

    /**
     * @param CollectionFactory $linkCollectionFactory
     * @param EnumLookup $enumLookup
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ProductCollectionFactory $collectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param CollectionPostProcessor $collectionPostProcessor
     * @param DataPostProcessor $dataPostProcessor
     */
    public function __construct(
        private readonly CollectionFactory $linkCollectionFactory,
        private readonly EnumLookup $enumLookup,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ProductCollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly CollectionPostProcessor $collectionPostProcessor,
        private readonly DataPostProcessor $dataPostProcessor
    ) {}

    /**
     * add option and id filter pair to filter for fetch.
     * @param int $optionId
     * @param int $parentId
     * @return void
     */
    public function addIdFilters(int $optionId, int $parentId): void
    {
        if (!in_array($optionId, $this->optionIds)) {
            $this->optionIds[] = $optionId;
        }

        if (!in_array($parentId, $this->parentIds)) {
            $this->parentIds[] = $parentId;
        }
    }

    /**
     * @param ResolveInfo $resolveInfo
     * @return void
     */
    public function addResolveInfo($resolveInfo)
    {
        $this->resolveInfo = $resolveInfo;
    }

    /**
     * retrieve links for passed in option id.
     * @param int $optionId
     * @return array
     * @throws RuntimeException
     * @throws Zend_Db_Select_Exception
     */
    public function getLinksForOptionId(int $optionId): array
    {
        $linksList = $this->fetch();

        if (!isset($linksList[$optionId])) {
            return [];
        }

        return $linksList[$optionId];
    }

    /**
     * @param mixed $productIds
     * @return array
     */
    protected function getProductMap($productIds): array
    {
        $attributeData = $this->getFieldsFromProductInfo(
            $this->resolveInfo,
            'options/product'
        );

        $collection = $this->collectionFactory->create();

        // build a search criteria based on original one and filter of product ids
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $productIds, 'in')
            ->create();

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

        return $this->dataPostProcessor->process(
            $collection->getItems(),
            'options/product',
            $this->resolveInfo
        );
    }

    /**
     * fetch link data and return in array format. Keys for links will be their option Ids.
     * @return array
     * @throws RuntimeException
     * @throws Zend_Db_Select_Exception
     */
    private function fetch(): array
    {
        if (empty($this->optionIds) || empty($this->parentIds) || !empty($this->links)) {
            return $this->links;
        }

        $linkCollection = $this->linkCollectionFactory->create();
        $linkCollection->setOptionIdsFilter($this->optionIds);
        $field = 'parent_product_id';

        foreach ($linkCollection->getSelect()->getPart('from') as $tableAlias => $data) {
            if ($data['tableName'] === $linkCollection->getTable('catalog_product_bundle_selection')) {
                $field = $tableAlias . '.' . $field;
            }
        }

        $linkCollection->getSelect()
            ->where($field . ' IN (?)', $this->parentIds);

        $links = $linkCollection->getItems();
        $productIds = array_map(static function ($link) {
            /** @var Selection $link */
            return $link->getProductId();
        }, $links);

        $productMap = $this->getProductMap($productIds);

        /** @var Selection $link */
        foreach ($links as $link) {
            $data = $link->getData();
            $productId = $link->getProductId();
            $product = $productMap[$productId] ?? null;
            $formattedLink = [
                'price' => $link->getSelectionPriceValue(),
                'position' => $link->getPosition(),
                'id' => $link->getSelectionId(),
                'qty' => (float)$link->getSelectionQty(),
                'quantity' => (float)$link->getSelectionQty(),
                'is_default' => (bool)$link->getIsDefault(),
                'price_type' => $this->enumLookup->getEnumValueFromField(
                    'PriceTypeEnum',
                    (string)$link->getSelectionPriceType()
                ) ?: 'DYNAMIC',
                'can_change_quantity' => $link->getSelectionCanChangeQty(),
                'product' => $product
            ];

            $data = array_replace($data, $formattedLink);

            if (!isset($this->links[$link->getOptionId()])) {
                $this->links[$link->getOptionId()] = [];
            }

            $this->links[$link->getOptionId()][] = $data;
        }

        return $this->links;
    }
}
