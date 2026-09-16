<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright © 2018 Scandiweb, Ltd (https://scandiweb.com)
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

namespace ScandiPWA\CatalogGraphQl\Model\ResourceModel\Fulltext\Collection;

use Magento\Elasticsearch\Model\ResourceModel\Fulltext\Collection\DefaultFilterStrategyApplyChecker as SourceDefaultFilterStrategyApplyCheckerAlias;

class DefaultFilterStrategyApplyChecker extends SourceDefaultFilterStrategyApplyCheckerAlias
{
    /**
     * check if this strategy applicable for current engine.
     * @return bool
     */
    public function isApplicable(): bool
    {
        return true;
    }
}
