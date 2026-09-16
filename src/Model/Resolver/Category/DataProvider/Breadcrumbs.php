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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Category\DataProvider;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\CatalogGraphQl\Model\Resolver\Category\DataProvider\Breadcrumbs as CoreBreadcrumbs;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Query\Uid;

class Breadcrumbs extends CoreBreadcrumbs
{
    /**
     * @param CollectionFactory $collectionFactory
     * @param Uid $uidEncoder
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly Uid $uidEncoder
    ) {
        parent::__construct($collectionFactory, $uidEncoder);
    }

    /**
     * get breadcrumbs data
     * @param string $categoryPath
     * @return array
     * @throws LocalizedException
     */
    public function getData(string $categoryPath): array
    {
        $breadcrumbsData = [];

        $pathCategoryIds = explode('/', $categoryPath);
        $parentCategoryIds = array_slice($pathCategoryIds, 2, -1);

        if (count($parentCategoryIds)) {
            $collection = $this->collectionFactory->create();
            $collection->addAttributeToSelect(['name', 'url_key', 'url_path', 'is_active']);
            $collection->addAttributeToFilter('entity_id', $parentCategoryIds);

            foreach ($collection as $category) {
                $breadcrumbsData[] = [
                    'category_id' => $category->getId(),
                    'category_uid' => $this->uidEncoder->encode((string)$category->getId()),
                    'category_name' => $category->getName(),
                    'category_level' => $category->getLevel(),
                    'category_url_key' => $category->getUrlKey(),
                    'category_url_path' => $category->getUrlPath(),
                    // the only change to fix breadcrumbs
                    'category_url' => parse_url($category->getUrl(), PHP_URL_PATH),
                    'category_is_active' => (bool)$category->getIsActive()
                ];
            }
        }
        return $breadcrumbsData;
    }
}
