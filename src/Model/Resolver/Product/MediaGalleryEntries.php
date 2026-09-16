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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Product;

use Magento\Catalog\Helper\Image as HelperFactory;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Area;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;

class MediaGalleryEntries implements ResolverInterface
{
    /**
     * @param ValueFactory $valueFactory
     * @param StoreManagerInterface $storeManager
     * @param HelperFactory $helperFactory
     * @param Emulation $emulation
     */
    public function __construct(
        private readonly ValueFactory $valueFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly HelperFactory $helperFactory,
        private readonly Emulation $emulation
    ) {}

    /**
     * @param mixed $mediaGalleryEntry
     * @param string $imageId
     * @param string $type
     * @return array
     */
    protected function getImageOfType(
        $mediaGalleryEntry,
        $imageId,
        $type
    ) {
        $image = $this->helperFactory->init($mediaGalleryEntry, $imageId, ['type' => $type])
            ->setImageFile($mediaGalleryEntry->getData('file'))
            ->constrainOnly(true)
            ->keepAspectRatio(true)
            ->keepTransparency(true)
            ->keepFrame(false);

        $url = $image->getUrl();

        return [
            'url' => $url,
            'type' => $type
        ];
    }

    /**
     * format product's media gallery entry data to conform to GraphQL schema
     * {@inheritdoc}
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): Value {
        if (!isset($value['model'])) {
            $result = function () {
                return null;
            };

            return $this->valueFactory->create($result);
        }

        /** @var Product $product */
        $product = $value['model'];
        $mediaGalleryEntries = [];

        if (!empty($product->getMediaGalleryEntries())) {
            $storeId = $this->storeManager->getStore()->getId();
            $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

            foreach ($product->getMediaGalleryEntries() as $key => $entry) {
                $thumbnail = $this->getImageOfType($entry, 'scandipwa_media_thumbnail', 'thumbnail');
                $base = $this->getImageOfType($entry, 'scandipwa_media_base', 'small_image');
                $large = $this->getImageOfType($entry, 'scandipwa_media_large', 'large');
                $mediaGalleryEntries[$key] = $entry->getData()
                    + ['thumbnail' => $thumbnail, 'base' => $base, 'large' => $large];

                if ($entry->getExtensionAttributes() && $entry->getExtensionAttributes()->getVideoContent()) {
                    $mediaGalleryEntries[$key]['video_content']
                        = $entry->getExtensionAttributes()->getVideoContent()->getData();
                }
            }

            $this->emulation->stopEnvironmentEmulation();
        }

        $result = function () use ($mediaGalleryEntries) {
            return $mediaGalleryEntries;
        };

        return $this->valueFactory->create($result);
    }
}
