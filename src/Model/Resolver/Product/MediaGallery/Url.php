<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright 2019 Adobe. All Rights Reserved.
 * @copyright   Copyright © Scandiweb, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Product\MediaGallery;

use Exception;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\ImageFactory;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @deprecated superseded by `MediaGalleryEntry.thumbnail`, `base` and `large`, which carry the url with its type
 */
class Url implements ResolverInterface
{
    /**
     * @param ImageFactory $productImageFactory
     * @param Image $imageHelper
     * @param Emulation $emulation
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ImageFactory $productImageFactory,
        private readonly Image $imageHelper,
        private readonly Emulation $emulation,
        private readonly StoreManagerInterface $storeManager
    ) {}

    /**
     * fetches the data from persistence models and format it according to the GraphQL schema.
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array|string
     * @throws Exception
     * @throws LocalizedException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (!isset($value['image_type']) && !isset($value['file'])) {
            throw new LocalizedException(__('"image_type" value should be specified'));
        }

        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        /** @var Product $product */
        $product = $value['model'];
        $storeId = $this->storeManager->getStore()->getId();

        if (isset($value['image_type'])) {
            $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

            $image = $this->imageHelper
                ->init(
                    $product,
                    sprintf('scandipwa_%s', $value['image_type']),
                    ['type' => $value['image_type']]
                )
                ->constrainOnly(true)
                ->keepAspectRatio(true)
                ->keepTransparency(true)
                ->keepFrame(false);

            $imageUrl = $image->getUrl();

            $this->emulation->stopEnvironmentEmulation();

            return $imageUrl;
        }

        if (isset($value['file'])) {
            $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

            $image = $this->productImageFactory->create();
            $image->setDestinationSubdir('image')->setBaseFile($value['file']);
            $imageUrl = $image->getUrl();

            $this->emulation->stopEnvironmentEmulation();
            return $imageUrl;
        }

        return [];
    }
}
