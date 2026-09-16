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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor\VisibilityStatusProcessor as CoreVisibilityStatusProcessor;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\GraphQl\Model\Query\ContextInterface;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CriteriaCheck;

class VisibilityStatusProcessor extends CoreVisibilityStatusProcessor
{
    /**
     * process collection, skipping the visibility join when searchCriteria already filters on it
     * @param Collection $collection
     * @param SearchCriteriaInterface $searchCriteria
     * @param array $attributeNames
     * @param ContextInterface|null $context
     * @return Collection
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws LocalizedException
     */
    public function process(
        Collection $collection,
        SearchCriteriaInterface $searchCriteria,
        array $attributeNames,
        ?ContextInterface $context = null
    ): Collection {
        $collection->joinAttribute('status', 'catalog_product/status', 'entity_id', null, 'inner');

        $visibilityFilter = CriteriaCheck::getVisibilityFilter($searchCriteria);

        if (!$visibilityFilter) {
            $collection->joinAttribute('visibility', 'catalog_product/visibility', 'entity_id', null, 'inner');
        }

        // the parent scopes by website only here, so skipping the join would drop the scope
        if ($context) {
            $store = $context->getExtensionAttributes()->getStore();

            if ($store) {
                $collection->addWebsiteFilter([$store->getWebsiteId()]);
            }
        }

        return $collection;
    }
}
