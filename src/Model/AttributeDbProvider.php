<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright © 2019 Scandiweb, Ltd (https://scandiweb.com)
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

namespace ScandiPWA\CatalogGraphQl\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\GraphQl\Query\Fields;
use Zend_Db_Statement_Exception;

class AttributeDbProvider
{
    /**
     * @param ResourceConnection $connection
     * @param Fields $queryFields
     */
    public function __construct(
        private readonly ResourceConnection $connection,
        private readonly Fields $queryFields
    ) {}

    /**
     * returns array of valid attributes, corresponding to request
     * @throws Zend_Db_Statement_Exception
     * @return array
     */
    public function getProductAttributes(): array
    {
        $fieldsUsedInQuery = $this->queryFields->getFieldsUsedInQuery();
        $connection = $this->connection->getConnection();
        $placeHolders = str_repeat('?,', count($fieldsUsedInQuery) - 1) . '?';
        $eavAttribute = $this->connection->getTableName('eav_attribute');
        $catalogEavAttribute = $this->connection->getTableName('catalog_eav_attribute');
        $sql = "SELECT $eavAttribute.attribute_code
        FROM {$eavAttribute}
        WHERE $eavAttribute.attribute_id IN (
            SELECT $catalogEavAttribute.attribute_id
            FROM {$catalogEavAttribute}
            WHERE $catalogEavAttribute.is_filterable = 1
        ) AND $eavAttribute.attribute_code IN ($placeHolders)";
        $query = $connection->query($sql, array_keys($fieldsUsedInQuery));

        return $query->fetchAll(\PDO::FETCH_COLUMN);
    }
}
