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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\Search\DocumentFactory;
use Magento\Framework\Api\Search\SearchResultFactory as FrameworkSearchResultFactory;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class EmulateSearchResult
{
    /**
     * @param AttributeValueFactory $attributeValueFactory
     * @param DocumentFactory $documentFactory
     * @param FrameworkSearchResultFactory $frameworkSearchResultFactory
     * @param ProductResource $productResource
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly AttributeValueFactory $attributeValueFactory,
        private readonly DocumentFactory $documentFactory,
        private readonly FrameworkSearchResultFactory $frameworkSearchResultFactory,
        private readonly ProductResource $productResource,
        private readonly StoreManagerInterface $storeManager
    ) {}

    /**
     * emulates ES search response for specific ID query
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultInterface
     * @throws NoSuchEntityException
     */
    public function execute(SearchCriteriaInterface $searchCriteria): SearchResultInterface
    {
        $idFilterValue = self::getIdFilterValue($searchCriteria);

        if (!$this->isVisible($idFilterValue, $searchCriteria)) {
            $itemsResults = $this->frameworkSearchResultFactory->create();
            $itemsResults->setItems([]);
            $itemsResults->setTotalCount(0);
            $itemsResults->setSearchCriteria($searchCriteria);

            return $itemsResults;
        }

        $scoreAttribute = $this->attributeValueFactory->create();
        $scoreAttribute->setAttributeCode('_score');
        $scoreAttribute->setValue(null);

        $document = $this->documentFactory->create();
        $document->setId($idFilterValue);
        $document->setCustomAttribute('score', $scoreAttribute);

        $itemsResults = $this->frameworkSearchResultFactory->create();
        $itemsResults->setItems([$document]);
        $itemsResults->setTotalCount(1);
        $itemsResults->setSearchCriteria($searchCriteria);

        return $itemsResults;
    }

    /**
     * checks the requested product's visibility against the search criteria filter
     * @param int|string|null $productId
     * @param SearchCriteriaInterface $searchCriteria
     * @return bool
     * @throws NoSuchEntityException
     */
    protected function isVisible($productId, SearchCriteriaInterface $searchCriteria): bool
    {
        $visibilityFilter = CriteriaCheck::getVisibilityFilter($searchCriteria);

        if ($productId === null || !$visibilityFilter) {
            return true;
        }

        $allowedVisibilityIds = array_map('intval', (array)$visibilityFilter->getValue());
        $storeId = (int)$this->storeManager->getStore()->getId();
        $actualVisibility = (int)$this->productResource->getAttributeRawValue(
            $productId,
            'visibility',
            $storeId
        );

        return in_array($actualVisibility, $allowedVisibilityIds, true);
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return string|null
     */
    static public function getIdFilterValue(SearchCriteriaInterface $searchCriteria)
    {
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            $filters = $filterGroup->getFilters();

            foreach ($filters as $filter) {
                $type = $filter->getConditionType();
                $field = $filter->getField();

                if ($type === 'eq' && $field === 'id') {
                    return $filter->getValue();
                }
            }
        }

        return null;
    }
}
