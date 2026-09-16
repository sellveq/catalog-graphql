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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Links\Collection;

class BundleItemLinks implements ResolverInterface
{
    /**
     * @param Collection $linkCollection
     * @param ValueFactory $valueFactory
     */
    public function __construct(
        private readonly Collection $linkCollection,
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
        if (!isset($value['option_id'], $value['parent_id'])) {
            throw new LocalizedException(__('"option_id" and "parent_id" values should be specified'));
        }

        $this->linkCollection->addIdFilters((int)$value['option_id'], (int)$value['parent_id']);

        $result = function () use ($value, $info) {
            $this->linkCollection->addResolveInfo($info);

            return $this->linkCollection->getLinksForOptionId((int)$value['option_id']);
        };

        return $this->valueFactory->create($result);
    }
}
