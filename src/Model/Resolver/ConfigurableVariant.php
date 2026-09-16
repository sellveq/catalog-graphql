<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright © 2019 Scandiweb, Ltd (https://scandiweb.com)
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as Type;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use ScandiPWA\CatalogGraphQl\Model\Variant\Collection;
use ScandiPWA\Performance\Model\Resolver\ResolveInfoFieldsTrait;

class ConfigurableVariant implements ResolverInterface
{
    use ResolveInfoFieldsTrait;

    /**
     * @param Collection $variantCollection
     * @param ValueFactory $valueFactory
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        protected readonly Collection $variantCollection,
        protected readonly ValueFactory $valueFactory,
        protected readonly MetadataPool $metadataPool
    ) {}

    /**
     * {@inheritdoc}
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();

        if ($value['type_id'] !== Type::TYPE_CODE || !isset($value[$linkField])) {
            $result = function () {
                return null;
            };

            return $this->valueFactory->create($result);
        }

        /** @var SearchCriteriaInterface $searchCriteria */
        $searchCriteria = $context->getExtensionAttributes()->getSearchCriteria('search_criteria');

        if ($searchCriteria) {
            $this->variantCollection->setSearchCriteria($searchCriteria);
        }

        $this->variantCollection->addParentProduct($value['model']);
        $fields = $this->getFieldsFromProductInfo($info, 'variants/product');
        $this->variantCollection->addEavAttributes($fields);

        $result = function () use ($value, $linkField, $info) {
            return $this->variantCollection->getChildProductsByParentId(
                (int)$value[$linkField],
                $info
            );
        };

        return $this->valueFactory->create($result);
    }
}
