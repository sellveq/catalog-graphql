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

namespace ScandiPWA\CatalogGraphQl\Plugin;

use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\App\AreaList;
use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State;

class InitGraphQlTranslations
{
    /**
     * @param AreaList $areaList
     * @param State $appState
     */
    public function __construct(
        private readonly AreaList $areaList,
        private readonly State $appState
    ) {}

    /**
     * initialize the translation area part, since GraphQL requests never trigger it otherwise.
     * @param FrontControllerInterface $subject
     * @param RequestInterface $request
     * @return void
     * @throws Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeDispatch(
        FrontControllerInterface $subject,
        RequestInterface $request
    ) {
        $area = $this->areaList->getArea($this->appState->getAreaCode());
        $area?->load(Area::PART_TRANSLATE);
    }
}
