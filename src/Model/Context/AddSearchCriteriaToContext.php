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

namespace ScandiPWA\CatalogGraphQl\Model\Context;

use Magento\GraphQl\Model\Query\ContextParametersInterface;
use Magento\GraphQl\Model\Query\ContextParametersProcessorInterface;

class AddSearchCriteriaToContext implements ContextParametersProcessorInterface
{
    /**
     * {@inheritdoc}
     */
    public function execute(
        ContextParametersInterface $contextParameters
    ): ContextParametersInterface {
        $contextParameters->addExtensionAttribute('search_criteria', null);
        return $contextParameters;
    }
}
