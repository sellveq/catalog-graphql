<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright 2014 Adobe. All Rights Reserved.
 * @copyright   Copyright © Scandiweb, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

namespace ScandiPWA\CatalogGraphQl\Model\Rule\Condition;

use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogWidget\Model\Rule\Condition\Product as ProductCondition;
use Magento\Framework\Exception\NoSuchEntityException;

class Product extends ProductCondition
{
    /**
     * @param Attribute $attribute
     * @param Collection $collection
     * @return $this
     * @throws NoSuchEntityException
     */
    protected function addGlobalAttribute(
        Attribute $attribute,
        Collection $collection
    ) {
        switch ($attribute->getBackendType()) {
            case 'decimal':
            case 'datetime':
            case 'int':
                $alias = 'at_' . $attribute->getAttributeCode();
                $collection->addAttributeToSelect($attribute->getAttributeCode(), 'left');
                break;
            default:
                $alias = 'at_' . sha1($this->getId()) . $attribute->getAttributeCode();

                $connection = $this->_productResource->getConnection();
                $storeId = $connection->getIfNullSql($alias . '.store_id', $this->storeManager->getStore()->getId());
                $linkField = $attribute->getEntity()->getLinkField();

                $collection->getSelect()->joinLeft(
                    [$alias => $collection->getTable($attribute->getBackendTable())],
                    "($alias.$linkField = e.$linkField) AND ($alias.store_id = $storeId)" .
                        " AND ($alias.attribute_id = {$attribute->getId()})",
                    []
                );
        }

        $this->joinedAttributes[$attribute->getAttributeCode()] = $alias . '.value';

        return $this;
    }
}
