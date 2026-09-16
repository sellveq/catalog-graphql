<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright 2018 Adobe. All Rights Reserved.
 * @copyright   Copyright © Scandiweb, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider;

use Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product as MagentoProduct;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionPostProcessor as MagentoCollectionPostProcessor;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\GraphQl\Model\Query\ContextInterface;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CriteriaCheck;
use ScandiPWA\Performance\Model\Resolver\Products\CollectionPostProcessor;

class Product extends MagentoProduct
{
    /**
     * @param CollectionFactory $collectionFactory
     * @param ProductSearchResultsInterfaceFactory $searchResultsFactory
     * @param Visibility $visibility
     * @param CollectionProcessorInterface $collectionProcessor
     * @param MagentoCollectionPostProcessor $magentoCollectionPostProcessor
     * @param CollectionPostProcessor $collectionPostProcessor
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ProductSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly Visibility $visibility,
        private readonly CollectionProcessorInterface $collectionProcessor,
        MagentoCollectionPostProcessor $magentoCollectionPostProcessor,
        private readonly CollectionPostProcessor $collectionPostProcessor
    ) {
        parent::__construct(
            $collectionFactory,
            $searchResultsFactory,
            $visibility,
            $collectionProcessor,
            $magentoCollectionPostProcessor
        );
    }

    /**
     * gets list of product data with full data set. Adds eav attributes to result set from passed in array
     * @param SearchCriteriaInterface $searchCriteria
     * @param string[] $attributes
     * @param bool $isSearch
     * @param bool $isChildSearch
     * @param ContextInterface|null $context
     * @return SearchResultsInterface
     */
    public function getList(
        SearchCriteriaInterface $searchCriteria,
        array $attributes = [],
        bool $isSearch = false,
        bool $isChildSearch = false,
        ?ContextInterface $context = null
    ): SearchResultsInterface {
        $collection = $this->collectionFactory->create();

        $this->collectionProcessor->process($collection, $searchCriteria, $attributes, $context);

        if (!$isChildSearch) {
            $singleProduct = CriteriaCheck::isSingleProductFilter($searchCriteria);

            if ($singleProduct) {
                $visibilityIds = $this->visibility->getVisibleInSiteIds();
            } else {
                $visibilityIds = $isSearch
                    ? $this->visibility->getVisibleInSearchIds()
                    : $this->visibility->getVisibleInCatalogIds();
            }

            $collection->setVisibility($visibilityIds);
        }

        $collection->load();
        $this->collectionPostProcessor->process($collection, $attributes);

        $searchResult = $this->searchResultsFactory->create();
        $searchResult->setSearchCriteria($searchCriteria);
        $searchResult->setItems($collection->getItems());
        $searchResult->setTotalCount($collection->getSize());
        return $searchResult;
    }
}
