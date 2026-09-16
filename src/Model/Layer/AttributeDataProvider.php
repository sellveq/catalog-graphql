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

namespace ScandiPWA\CatalogGraphQl\Model\Layer;

use Magento\Framework\App\ResourceConnection;

class AttributeDataProvider
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {}

    /**
     * @param string $attributeCode
     * @param int $storeId
     * @return array
     */
    public function getAttributeData($attributeCode, $storeId)
    {
        $connection = $this->resourceConnection->getConnection();

        $select = $connection->select()
            ->from(
                ['attribute' => $this->resourceConnection->getTableName('eav_attribute')]
            )
            ->joinLeft(
                ['attribute_label' => $this->resourceConnection->getTableName('eav_attribute_label')],
                "attribute.attribute_id = attribute_label.attribute_id AND attribute_label.store_id = $storeId",
                [
                    'attribute_store_label' => 'attribute_label.value',
                ]
            )
            ->where('attribute.attribute_code = ?', $attributeCode);

        return $connection->fetchRow($select);
    }
}
