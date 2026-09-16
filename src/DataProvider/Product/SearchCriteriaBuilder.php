<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright © 2022 Scandiweb, Ltd (https://scandiweb.com)
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\DataProvider\Product;

use Magento\Catalog\Api\Data\EavAttributeInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Layer\Filter\Dynamic\AlgorithmFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogGraphQl\DataProvider\Product\RequestDataBuilder;
use Magento\CatalogGraphQl\DataProvider\Product\SearchCriteriaBuilder as MagentoSearchCriteriaBuilder;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection\SearchCriteriaResolverFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\GraphQl\Query\Resolver\Argument\SearchCriteria\ArgumentApplierPool;
use Magento\Framework\GraphQl\Query\Resolver\Argument\SearchCriteria\Builder;
use Magento\Framework\Search\Request\Config as SearchConfig;
use Magento\Store\Model\ScopeInterface;

class SearchCriteriaBuilder extends MagentoSearchCriteriaBuilder
{
    /**
     * @param Builder $builder
     * @param ScopeConfigInterface $scopeConfig
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param Visibility $visibility
     * @param SortOrderBuilder $sortOrderBuilder
     * @param ProductAttributeRepositoryInterface $productAttributeRepository
     * @param SearchConfig $searchConfig
     * @param RequestDataBuilder $localData
     * @param SearchCriteriaResolverFactory $criteriaResolverFactory
     * @param ArgumentApplierPool $argumentApplierPool
     * @param array $partialSearchAnalyzers
     */
    public function __construct(
        private readonly Builder $builder,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly FilterBuilder $filterBuilder,
        private readonly FilterGroupBuilder $filterGroupBuilder,
        private readonly Visibility $visibility,
        private readonly SortOrderBuilder $sortOrderBuilder,
        ProductAttributeRepositoryInterface $productAttributeRepository,
        SearchConfig $searchConfig,
        RequestDataBuilder $localData,
        SearchCriteriaResolverFactory $criteriaResolverFactory,
        ArgumentApplierPool $argumentApplierPool,
        array $partialSearchAnalyzers = []
    ) {
        parent::__construct(
            $scopeConfig,
            $filterBuilder,
            $filterGroupBuilder,
            $visibility,
            $sortOrderBuilder,
            $productAttributeRepository,
            $searchConfig,
            $localData,
            $criteriaResolverFactory,
            $argumentApplierPool,
            $partialSearchAnalyzers
        );
    }

    /**
     * build search criteria
     * @param array $args
     * @param bool $includeAggregation
     * @return SearchCriteriaInterface
     */
    public function build(array $args, bool $includeAggregation): SearchCriteriaInterface
    {
        $searchCriteria = $this->builder->build('products', $args);
        $isSearch = !empty($args['search']);
        $this->updateRangeFilters($searchCriteria);

        if ($includeAggregation) {
            $this->preparePriceAggregation($searchCriteria);
            $requestName = 'graphql_product_search_with_aggregation';
        } else {
            $requestName = 'graphql_product_search';
        }

        $searchCriteria->setRequestName($requestName);

        if ($isSearch) {
            $this->addFilter($searchCriteria, 'search_term', $args['search']);
        }

        if (!$searchCriteria->getSortOrders()) {
            $this->addDefaultSortOrder($searchCriteria, $args, $isSearch);
        }

        $this->addEntityIdSort($searchCriteria, $args);
        $this->addVisibilityFilter($searchCriteria, $isSearch);

        // Framework\Search\Search offsets by currentPage * pageSize, so the engine counts pages from zero
        $searchCriteria->setCurrentPage($args['currentPage'] - 1);
        $searchCriteria->setPageSize($args['pageSize']);

        return $searchCriteria;
    }

    /**
     * add filter by visibility, always rather than conditionally
     * @param SearchCriteriaInterface $searchCriteria
     * @param bool $isSearch
     * @return void
     */
    protected function addVisibilityFilter(SearchCriteriaInterface $searchCriteria, bool $isSearch): void
    {
        // core applies this only to the search path; the storefront needs it on the filter path too
        $visibilityIds = $isSearch
            ? $this->visibility->getVisibleInSearchIds()
            : $this->visibility->getVisibleInCatalogIds();

        $this->addFilter($searchCriteria, 'visibility', $visibilityIds, 'in');
    }

    /**
     * add sort by Entity ID
     * @param SearchCriteriaInterface $searchCriteria
     * @param array $args
     * @return void
     */
    protected function addEntityIdSort(SearchCriteriaInterface $searchCriteria, array $args): void
    {
        $sortOrder = !empty($args['sort']) ? reset($args['sort']) : SortOrder::SORT_DESC;
        $sortOrderArray = $searchCriteria->getSortOrders();
        $sortOrderArray[] = $this->sortOrderBuilder
            ->setField('_id')
            ->setDirection($sortOrder)
            ->create();
        $searchCriteria->setSortOrders($sortOrderArray);
    }

    /**
     * prepare price aggregation algorithm
     * @param SearchCriteriaInterface $searchCriteria
     * @return void
     */
    protected function preparePriceAggregation(SearchCriteriaInterface $searchCriteria): void
    {
        $priceRangeCalculation = $this->scopeConfig->getValue(
            AlgorithmFactory::XML_PATH_RANGE_CALCULATION,
            ScopeInterface::SCOPE_STORE
        );

        if ($priceRangeCalculation) {
            $this->addFilter($searchCriteria, 'price_dynamic_algorithm', $priceRangeCalculation);
        }
    }

    /**
     * add filter to search criteria
     * @param SearchCriteriaInterface $searchCriteria
     * @param string $field
     * @param mixed $value
     * @param string|null $condition
     * @return void
     */
    protected function addFilter(
        SearchCriteriaInterface $searchCriteria,
        string $field,
        $value,
        ?string $condition = null
    ): void {
        $filter = $this->filterBuilder
            ->setField($field)
            ->setValue($value)
            ->setConditionType($condition)
            ->create();

        $this->filterGroupBuilder->addFilter($filter);
        $filterGroups = $searchCriteria->getFilterGroups();
        $filterGroups[] = $this->filterGroupBuilder->create();
        $searchCriteria->setFilterGroups($filterGroups);
    }

    /**
     * sort by relevance DESC by default
     * @param SearchCriteriaInterface $searchCriteria
     * @param array $args
     * @param bool $isSearch
     * @return void
     */
    protected function addDefaultSortOrder(
        SearchCriteriaInterface $searchCriteria,
        array $args,
        $isSearch = false
    ): void
    {
        $defaultSortOrder = [];

        if ($isSearch) {
            $defaultSortOrder[] = $this->sortOrderBuilder
                ->setField('relevance')
                ->setDirection(SortOrder::SORT_DESC)
                ->create();
        } else {
            $categoryIdFilter = $args['filter']['category_id'] ?? false;

            if ($categoryIdFilter) {
                if (
                    !is_array($categoryIdFilter[array_key_first($categoryIdFilter)])
                    || count($categoryIdFilter[array_key_first($categoryIdFilter)]) <= 1
                ) {
                    $defaultSortOrder[] = $this->sortOrderBuilder
                        ->setField(EavAttributeInterface::POSITION)
                        ->setDirection(SortOrder::SORT_ASC)
                        ->create();
                }
            }
        }

        $searchCriteria->setSortOrders($defaultSortOrder);
    }

    /**
     * format range filters so '%field.from%' and '%field.to%' placeholders get replaced
     * @param SearchCriteriaInterface $searchCriteria
     * @return void
     */
    protected function updateRangeFilters(SearchCriteriaInterface $searchCriteria): void
    {
        $filterGroups = $searchCriteria->getFilterGroups();

        foreach ($filterGroups as $filterGroup) {
            $filters = $filterGroup->getFilters();

            foreach ($filters as $filter) {
                if (in_array($filter->getConditionType(), ['from', 'to'])) {
                    $filter->setField($filter->getField() . '.' . $filter->getConditionType());
                }
            }
        }
    }
}
