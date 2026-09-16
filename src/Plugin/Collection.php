<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright © Magento, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

namespace ScandiPWA\CatalogGraphQl\Plugin;

use Closure;
use Exception;
use Magento\Eav\Model\Entity\Collection\AbstractCollection;
use Magento\Framework\DataObject;

class Collection
{
    /**
     * @param AbstractCollection $subject
     * @param Closure $process
     * @param DataObject $dataObject
     * @return AbstractCollection
     */
    public function aroundAddItem(
        AbstractCollection $subject,
        Closure $process,
        DataObject $dataObject
    ) {
        try {
            return $process($dataObject);
        } catch (Exception) {
            return $subject;
        }
    }
}
