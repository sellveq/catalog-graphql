<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\MenuItems;

use Magento\Catalog\Model\Category;
use Magento\Framework\GraphQl\Query\Resolver\IdentityInterface;

class Identity implements IdentityInterface
{
    /**
     * {@inheritdoc}
     */
    public function getIdentities(array $resolvedData): array
    {
        $ids = [];

        foreach ($resolvedData as $item) {
            // the synthetic root row carries the store's root category in item_id and 0 in category_id
            $categoryId = $item['item_id'] ?? null;

            if ($categoryId === null) {
                continue;
            }

            $ids[] = sprintf('%s_%s', Category::CACHE_TAG, $categoryId);
        }

        if (!$ids) {
            return [];
        }

        array_unshift($ids, Category::CACHE_TAG);

        return $ids;
    }
}
