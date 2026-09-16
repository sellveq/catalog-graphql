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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\Query;

use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product as ProductProvider;
use Magento\CatalogGraphQl\Model\Resolver\Products\Query\FieldSelection;
use Magento\CatalogGraphQl\Model\Resolver\Products\Query\Filter as MagentoFilter;
use Magento\CatalogGraphQl\Model\Resolver\Products\SearchResult;
use Magento\CatalogGraphQl\Model\Resolver\Products\SearchResultFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\GraphQl\Query\Resolver\Argument\SearchCriteria\Builder as SearchCriteriaBuilder;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Search\Model\Query;
use Magento\Store\Model\ScopeInterface;
use ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;

class Filter extends MagentoFilter
{
    /**
     * @param SearchResultFactory $searchResultFactory
     * @param ProductProvider $productDataProvider
     * @param FieldSelection $fieldSelection
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ScopeConfigInterface $scopeConfig
     * @param DataPostProcessor $productPostProcessor
     */
    public function __construct(
        private readonly SearchResultFactory $searchResultFactory,
        private readonly ProductProvider $productDataProvider,
        private readonly FieldSelection $fieldSelection,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly DataPostProcessor $productPostProcessor
    ) {
        parent::__construct(
            $searchResultFactory,
            $productDataProvider,
            $fieldSelection,
            $searchCriteriaBuilder,
            $scopeConfig
        );
    }

    /**
     * filter catalog product data based off given search criteria
     * @param array $args
     * @param ResolveInfo $info
     * @param ContextInterface $context
     * @return SearchResult
     */
    public function getResult(
        array $args,
        ResolveInfo $info,
        ContextInterface $context
    ): SearchResult {
        $fields = $this->fieldSelection->getProductsFieldSelection($info);

        try {
            $searchCriteria = $this->buildSearchCriteria($args, $info);
            $searchResults = $this->productDataProvider->getList($searchCriteria, $fields, false, false, $context);
        } catch (InputException) {
            return $this->createEmptyResult($args);
        }

        $productArray = $this->productPostProcessor->process(
            $searchResults->getItems(),
            'products/items',
            $info
        );

        //possible division by 0
        if ($searchCriteria->getPageSize()) {
            $maxPages = (int)ceil($searchResults->getTotalCount() / $searchCriteria->getPageSize());
        } else {
            $maxPages = 0;
        }

        return $this->searchResultFactory->create(
            [
                'totalCount' => $searchResults->getTotalCount(),
                'productsSearchResult' => $productArray,
                'pageSize' => $searchCriteria->getPageSize(),
                'currentPage' => $searchCriteria->getCurrentPage(),
                'totalPages' => $maxPages,
            ]
        );
    }

    /**
     * build search criteria from query input args
     * @param array $args
     * @param ResolveInfo $info
     * @return SearchCriteriaInterface
     * @throws InputException
     */
    private function buildSearchCriteria(array $args, ResolveInfo $info): SearchCriteriaInterface
    {
        if (!empty($args['filter'])) {
            $args['filter'] = $this->formatFilters($args['filter']);
        }

        $criteria = $this->searchCriteriaBuilder->build($info->fieldName, $args);
        $criteria->setCurrentPage($args['currentPage']);
        $criteria->setPageSize($args['pageSize']);

        return $criteria;
    }

    /**
     * reformat filters
     * @param array $filters
     * @return array
     * @throws InputException
     */
    private function formatFilters(array $filters): array
    {
        $formattedFilters = [];
        $minimumQueryLength = $this->scopeConfig->getValue(
            Query::XML_PATH_MIN_QUERY_LENGTH,
            ScopeInterface::SCOPE_STORE
        );

        foreach ($filters as $field => $filter) {
            foreach ($filter as $condition => $value) {
                if ($condition === 'match') {
                    // reformat 'match' filter so MySQL filtering behaves like SearchAPI filtering
                    $condition = 'like';
                    $value = $value !== null ? str_replace('%', '', trim($value)) : '';
                    if (strlen($value) < $minimumQueryLength) {
                        throw new InputException(__('Invalid match filter'));
                    }
                    $value = '%' . preg_replace('/ +/', '%', $value) . '%';
                }
                $formattedFilters[$field] = [$condition => $value];
            }
        }

        return $formattedFilters;
    }

    /**
     * return an empty SearchResult object, used to handle exceptions gracefully
     * @param array $args
     * @return SearchResult
     */
    private function createEmptyResult(array $args): SearchResult
    {
        return $this->searchResultFactory->create(
            [
                'totalCount' => 0,
                'productsSearchResult' => [],
                'pageSize' => $args['pageSize'],
                'currentPage' => $args['currentPage'],
                'totalPages' => 0,
            ]
        );
    }
}
