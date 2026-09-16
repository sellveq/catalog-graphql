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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Category;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Category\FileInfo;
use Magento\CatalogGraphQl\Model\Resolver\Category\Image as CoreImage;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class Image extends CoreImage
{
    public const string PLACEHOLDER_IMAGE = 'Magento_Catalog::images/category/placeholder/image.jpg';

    /**
     * @param DirectoryList $directoryList
     * @param FileInfo $fileInfo
     * @param Repository $assetRepo
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly DirectoryList $directoryList,
        private readonly FileInfo $fileInfo,
        private readonly Repository $assetRepo,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct(
            $directoryList,
            $fileInfo,
            $assetRepo,
            $logger
        );
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        /** @var Category $category */
        $category = $value['model'];
        $imagePath = $category->getData('image');
        if (empty($imagePath)) {
            return null;
        }

        /** @var StoreInterface $store */
        $store = $context->getExtensionAttributes()->getStore();
        $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_DIRECT_LINK);

        $filenameWithMedia =  $this->fileInfo->isBeginsWithMediaDirectoryPath($imagePath)
            ? $imagePath : $this->formatFileNameWithMediaCategoryFolder($imagePath);

        if (!$this->fileInfo->isExist($filenameWithMedia)) {
            $this->logger->error(__('Category image not found'));

            return $this->assetRepo
                ->createAsset(self::PLACEHOLDER_IMAGE, ['area' => Area::AREA_FRONTEND])
                ->getUrl();
        }

        // return full url
        return rtrim($baseUrl, '/') . $filenameWithMedia;
    }

    /**
     * format category media folder to filename
     * @param string $fileName
     * @return string
     */
    protected function formatFileNameWithMediaCategoryFolder(string $fileName): string
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $baseFileName = basename($fileName);

        return '/'
            . $this->directoryList->getUrlPath('media')
            . '/'
            . ltrim(FileInfo::ENTITY_MEDIA_PATH, '/')
            . '/'
            . $baseFileName;
    }
}
