<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright © Magento, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor;

use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\GraphQl\Model\Query\ContextInterface;

class ImagesProcessor implements CollectionProcessorInterface
{
    /**
     * @param MediaConfig $mediaConfig
     */
    public function __construct(
        private readonly MediaConfig $mediaConfig
    ) {}

    /**
     * {@inheritdoc}
     */
    public function process(
        Collection $collection,
        SearchCriteriaInterface $searchCriteria,
        array $attributeNames,
        ?ContextInterface $context = null
    ): Collection {
        $mediaAttributes = $this->mediaConfig->getMediaAttributeCodes();

        if (array_intersect($mediaAttributes, $attributeNames)) {
            $imagesToBeRequested = [];

            foreach ($mediaAttributes as $imageType) {
                if (isset($attributeNames[$imageType])) {
                    $imagesToBeRequested[] = $imageType;
                    $imagesToBeRequested[] = sprintf('%s_label', $imageType);
                }
            }

            $collection->addAttributeToSelect($imagesToBeRequested);
        }

        return $collection;
    }
}
