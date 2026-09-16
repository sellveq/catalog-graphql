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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Aggregations;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Aggregations\DataProvider\Swatches;

class SwatchData implements ResolverInterface
{
    /**
     * @param Swatches $swatches
     * @param ValueFactory $valueFactory
     */
    public function __construct(
        private readonly Swatches $swatches,
        private readonly ValueFactory $valueFactory
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
        $optionId = $value['value'];

        if (is_int($optionId)) {
            $this->swatches->addAttributeOptionId($optionId);
        }

        $result = function () use ($optionId) {
            $swatches = $this->swatches->getSwatchData();
            return $swatches[$optionId] ?? null;
        };

        return $this->valueFactory->create($result);
    }
}
