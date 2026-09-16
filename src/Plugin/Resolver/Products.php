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

namespace ScandiPWA\CatalogGraphQl\Plugin\Resolver;

use Magento\CatalogGraphQl\Model\Resolver\Products as CoreProducts;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\Argument\SearchCriteria\Builder;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class Products
{
    // core's only ceiling is the search engine's 10,000 window, which serialises the whole catalogue in one request
    public const int MAX_PAGE_SIZE = 500;

    /**
     * @param Builder $searchCriteriaBuilder
     */
    public function __construct(
        private readonly Builder $searchCriteriaBuilder
    ) {}

    /**
     * refuse an oversized page and put the criteria on the context for the resolvers that read it
     * @param CoreProducts $products
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlInputException
     */
    public function beforeResolve(
        CoreProducts $products,
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (isset($args['pageSize']) && $args['pageSize'] > self::MAX_PAGE_SIZE) {
            throw new GraphQlInputException(
                __(
                    'pageSize value %1 specified is greater than the maximum of %2.',
                    [$args['pageSize'], self::MAX_PAGE_SIZE]
                )
            );
        }

        $searchCriteria = $this->searchCriteriaBuilder->build('products', $args);
        $context->getExtensionAttributes()->setSearchCriteria($searchCriteria);

        return [
            $field,
            $context,
            $info,
            $value,
            $args
        ];
    }
}
