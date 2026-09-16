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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Aggregations\DataProvider;

use Magento\Swatches\Helper\Data;

class Swatches
{
    /**
     * @var array
     */
    protected $optionIds = [];

    /**
     * @var array
     */
    protected $swatchData = [];

    /**
     * @param Data $swatchHelper
     */
    public function __construct(
        private readonly Data $swatchHelper
    ) {}

    /**
     * @param int $optionId
     * @return void
     */
    public function addAttributeOptionId(int $optionId): void
    {
        $this->optionIds[] = $optionId;
    }

    /**
     * @return array
     */
    public function getSwatchData(): array
    {
        if (!count($this->swatchData)) {
            $this->swatchData = $this->swatchHelper->getSwatchesByOptionsId($this->optionIds);
        }

        return $this->swatchData;
    }
}
