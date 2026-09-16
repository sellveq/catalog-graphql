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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessorInterface;
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\GraphQl\Model\Query\ContextInterface;

class AttributeProcessor implements CollectionProcessorInterface
{
    /** Identifier for request type */
    public const string VARIANT_PLP_FIELD = 'variant_plp';

    /**
     * existing product entity attribute codes
     * @var array
     */
    protected $validAttributeCodes = [];

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {}

    /**
     * {@inheritdoc}
     */
    public function process(
        Collection $collection,
        SearchCriteriaInterface $searchCriteria,
        array $attributeNames,
        ?ContextInterface $context = null
    ): Collection {
        if (in_array(self::VARIANT_PLP_FIELD, $attributeNames)) {
            // the PLP variant load skips the attribute post processor, so the wildcard is fastest here
            $collection->addAttributeToSelect('*');

            return $collection;
        } else {
            return $this->processRegularCollection($collection, $attributeNames);
        }
    }

    /**
     * @param Collection $collection
     * @param array $attributeNames
     * @return Collection
     */
    public function processRegularCollection(Collection $collection, array $attributeNames): Collection
    {
        // $attributeNames lists every queried field, not just attributes
        $this->loadValidAttributeCodes();

        // individual calls beat a wildcard, which joins every attribute on collection afterLoad
        foreach ($attributeNames as $name) {
            if (array_key_exists($name, $this->validAttributeCodes)) {
                $collection->addAttributeToSelect($name);
            }
        }

        return $collection;
    }

    /**
     * load valid attribute codes via a raw select; the EAV collection is 20-30x slower
     * @return array
     */
    protected function loadValidAttributeCodes(): array
    {
        if (empty($this->validAttributeCodes)) {
            $connection = $this->resourceConnection->getConnection();
            $select = $connection->select();
            $select->from(
                $connection->getTableName('eav_attribute'),
                AttributeInterface::ATTRIBUTE_CODE
            )->joinInner(
                ['type' => $connection->getTableName('eav_entity_type')],
                'type.entity_type_id=eav_attribute.entity_type_id',
                []
            )->where('type.entity_type_code = ?', ProductAttributeInterface::ENTITY_TYPE_CODE);

            $this->validAttributeCodes = array_flip($connection->fetchCol($select));
        }

        return $this->validAttributeCodes;
    }
}
