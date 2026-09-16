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

namespace ScandiPWA\CatalogGraphQl\Plugin\ResourceModel;

use Magento\Catalog\Model\ResourceModel\Category as CoreCategory;
use Magento\Eav\Model\Entity\Context;
use Magento\Framework\App\ResourceConnection;

class Category
{
    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @param Context $context
     */
    public function __construct(
        Context $context
    ) {
        $this->resource = $context->getResource();
    }

    /**
     * @param CoreCategory $subject
     * @param callable $next
     * @param mixed $category
     * @return int
     */
    public function aroundGetProductCount(
        CoreCategory $subject,
        callable $next,
        $category
    ) {
        // the index table carries the resolved anchor tree, which the raw assignment table does not
        $productTable = $this->resource->getTableName('catalog_category_product_index');

        $select = $this->resource->getConnection()->select()->from(
            ['main_table' => $productTable],
            [new \Zend_Db_Expr('COUNT(main_table.product_id)')]
        )->where(
            'main_table.category_id = :category_id'
        );

        $bind = ['category_id' => (int)$category->getId()];
        $counts = $this->resource->getConnection()->fetchOne($select, $bind);

        return (int)$counts;
    }
}
