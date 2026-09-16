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

namespace ScandiPWA\CatalogGraphQl\Plugin;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\Category as CoreCategory;
use Magento\Catalog\Model\ResourceModel\Category as CoreResourceCategory;

class Category
{
    /**
     * @param CoreResourceCategory $resource
     */
    public function __construct(
        private readonly CoreResourceCategory $resource
    ) {}

    /**
     * @param CoreCategory $category
     * @param callable $next
     * @return mixed
     */
    public function aroundGetProductCount(
        CoreCategory $category,
        callable $next
    ) {
        if (!$category->hasData(CategoryInterface::KEY_PRODUCT_COUNT)) {
            $count = $this->resource->getProductCount($category);
            $category->setData(CategoryInterface::KEY_PRODUCT_COUNT, $count);
        }

        return $category->getData(CategoryInterface::KEY_PRODUCT_COUNT);
    }
}
