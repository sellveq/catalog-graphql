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

use Exception;
use Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\CatalogGraphQl\DataProvider\Product\SearchCriteriaBuilder;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\ProductSearch;
use Magento\CatalogGraphQl\Model\Resolver\Products\Query\FieldSelection;
use Magento\CatalogGraphQl\Model\Resolver\Products\Query\Search as CoreSearch;
use Magento\CatalogGraphQl\Model\Resolver\Products\Query\Search\QueryPopularity as CoreQueryPopularity;
use Magento\CatalogGraphQl\Model\Resolver\Products\Query\Suggestions;
use Magento\CatalogGraphQl\Model\Resolver\Products\SearchResult;
use Magento\CatalogGraphQl\Model\Resolver\Products\SearchResultFactory;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ArgumentsProcessorInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Search\Api\SearchInterface;
use Magento\Search\Model\QueryFactory;
use Magento\Search\Model\Search\PageSizeProvider;
use Magento\Store\Model\StoreManagerInterface;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CriteriaCheck;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\EmulateSearchResult;
use ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;

class Search extends CoreSearch
{
    /**
     * @param SearchInterface $search
     * @param SearchResultFactory $searchResultFactory
     * @param ProductSearchResultsInterfaceFactory $productSearchResultsInterfaceFactory
     * @param EmulateSearchResult $emulateSearchResult
     * @param PageSizeProvider $pageSize
     * @param FieldSelection $fieldSelection
     * @param ProductSearch $productsProvider
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param DataPostProcessor $productPostProcessor
     * @param QueryFactory $queryFactory
     * @param StoreManagerInterface $storeManager
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ArgumentsProcessorInterface $argsSelection
     * @param Suggestions|null $suggestions
     * @param CoreQueryPopularity|null $queryPopularity
     */
    public function __construct(
        private readonly SearchInterface $search,
        private readonly SearchResultFactory $searchResultFactory,
        private readonly ProductSearchResultsInterfaceFactory $productSearchResultsInterfaceFactory,
        private readonly EmulateSearchResult $emulateSearchResult,
        private readonly PageSizeProvider $pageSize,
        private readonly FieldSelection $fieldSelection,
        private readonly ProductSearch $productsProvider,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly DataPostProcessor $productPostProcessor,
        private readonly QueryFactory $queryFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly ArgumentsProcessorInterface $argsSelection,
        ?Suggestions $suggestions = null,
        ?CoreQueryPopularity $queryPopularity = null
    ) {
        parent::__construct(
            $search,
            $searchResultFactory,
            $fieldSelection,
            $productsProvider,
            $searchCriteriaBuilder,
            $argsSelection,
            $suggestions ?: ObjectManager::getInstance()->get(Suggestions::class),
            $queryPopularity ?: ObjectManager::getInstance()->get(CoreQueryPopularity::class)
        );
    }

    /**
     * return product search results using Search API
     * @param array $args
     * @param ResolveInfo $info
     * @param ContextInterface $context
     * @return SearchResult
     * @throws Exception
     */
    public function getResult(
        array $args,
        ResolveInfo $info,
        ContextInterface $context
    ): SearchResult {
        $queryFields = $this->fieldSelection->getProductsFieldSelection($info);
        $searchCriteria = $this->buildSearchCriteria($args, $info);
        $itemsResults = $this->getSearchResults($searchCriteria);

        // the category cache tag is added on load_after, so the filtered category has to be loaded
        if (!empty($args['filter']['category_id'])) {
            $this->categoryCollectionFactory->create()
                ->addAttributeToSelect('entity_id')
                ->addAttributeToFilter('entity_id', $args['filter']['category_id'])
                ->load();
        }

        if ($this->includeItems($info)) {
            // load product collection only if items are requested
            $searchResults = $this->productsProvider->getList(
                $searchCriteria,
                $itemsResults,
                $queryFields,
                $context
            );
        } else {
            $searchResults = $this->productSearchResultsInterfaceFactory->create();
            $searchResults->setSearchCriteria($searchCriteria);
            $searchResults->setTotalCount($itemsResults->getTotalCount());
        }

        $totalPages = $searchCriteria->getPageSize() ?
            ((int)ceil($searchResults->getTotalCount() / $searchCriteria->getPageSize())) : 0;

        if (!empty($args['search']) && strlen(trim($args['search']))) {
            $this->incrementQuery($args['search'], $searchResults->getTotalCount());
        }

        if (count($queryFields) > 0) {
            $productArray = $this->productPostProcessor->process(
                $searchResults->getItems(),
                'products/items',
                $info,
                ['isSingleProduct' => CriteriaCheck::isSingleProductFilter($searchCriteria)]
            );
        } else {
            $productArray = array_map(function ($product) {
                return $product->getData() + ['model' => $product];
            }, $searchResults->getItems());
        }

        return $this->searchResultFactory->create(
            [
                'totalCount' => $searchResults->getTotalCount(),
                'productsSearchResult' => $productArray,
                'searchAggregation' => $itemsResults->getAggregations(),
                'pageSize' => $searchCriteria->getPageSize(),
                'currentPage' => $args['currentPage'],
                'totalPages' => $totalPages,
            ]
        );
    }

    /**
     * 2.4.9's ProductSearch no longer re-pages the engine result, so the page must be asked of the engine
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultInterface
     * @throws NoSuchEntityException
     * @throws GraphQlInputException
     */
    private function getSearchResults(SearchCriteriaInterface $searchCriteria): SearchResultInterface
    {
        if (CriteriaCheck::isOnlySingleIdFilter($searchCriteria)) {
            return $this->emulateSearchResult->execute($searchCriteria);
        }

        $pageSize = $searchCriteria->getPageSize();
        $maxPageSize = $this->pageSize->getMaxPageSize();

        // the engine answers nothing past its result window, so a deeper page is refused, not mis-served
        if (($searchCriteria->getCurrentPage() + 1) * $pageSize > $maxPageSize) {
            throw new GraphQlInputException(
                __(
                    'currentPage value %1 specified is greater than the %2 page(s) the search engine can return.',
                    [$searchCriteria->getCurrentPage() + 1, (int)floor($maxPageSize / $pageSize)]
                )
            );
        }

        return $this->search->search($searchCriteria);
    }

    /**
     * build search criteria from query input args
     * @param array $args
     * @param ResolveInfo $info
     * @return SearchCriteriaInterface
     * @throws LocalizedException
     * @throws GraphQlInputException
     */
    private function buildSearchCriteria(array $args, ResolveInfo $info): SearchCriteriaInterface
    {
        $productFields = (array)$info->getFieldSelection(1);
        $fieldName = $info->fieldName ?? "";
        $processedArgs = $this->argsSelection->process((string)$fieldName, $args);
        return $this->searchCriteriaBuilder->build($processedArgs, $this->getIsIncludeAggregations($info));
    }

    /**
     * @param ResolveInfo $info
     * @return bool
     */
    private function getIsIncludeAggregations(ResolveInfo $info): bool
    {
        $productFields = (array)$info->getFieldSelection(1);

        return isset($productFields['filters']) || isset($productFields['aggregations']);
    }

    /**
     * @param ResolveInfo $info
     * @return bool
     */
    private function includeItems(ResolveInfo $info): bool
    {
        $productFields = (array)$info->getFieldSelection(1);

        return isset($productFields['items']);
    }

    /**
     * @param mixed $queryText
     * @param mixed $queryResultCount
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function incrementQuery($queryText, $queryResultCount)
    {
        $query = $this->queryFactory->get();
        $query->setQueryText($queryText);
        $query->setNumResults($queryResultCount);
        $query->setStoreId($this->storeManager->getStore()->getId());
        $query->saveIncrementalPopularity();
        $query->saveNumResults($queryResultCount);
    }
}
