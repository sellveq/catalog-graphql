<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright © Scandiweb, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Layer\Filter;

use Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\Formatter\LayerFormatter;
use Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\LayerBuilderInterface;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\BucketInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use ScandiPWA\CatalogGraphQl\Model\Layer\AttributeDataProvider;

class Price implements LayerBuilderInterface
{
    /**
     * @var string
     */
    public const string PRICE_BUCKET = 'price_bucket';

    /**
     * @var array
     */
    private static array $bucketMap = [
        self::PRICE_BUCKET => [
            'request_name' => 'price',
            'label' => 'Price'
        ],
    ];

    /**
     * @param LayerFormatter $layerFormatter
     * @param StoreManagerInterface $storeManager
     * @param AttributeDataProvider $attributeDataProvider
     */
    public function __construct(
        private readonly LayerFormatter $layerFormatter,
        private readonly StoreManagerInterface $storeManager,
        private readonly AttributeDataProvider $attributeDataProvider
    ) {}

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function build(AggregationInterface $aggregation, ?int $storeId): array
    {
        $bucket = $aggregation->getBucket(self::PRICE_BUCKET);
        if ($this->isBucketEmpty($bucket)) {
            return [];
        }

        // core hard-codes the English bucket label, so the store-scoped attribute label wins over it
        $attributeData = $this->attributeDataProvider->getAttributeData('price', $storeId);
        $attributeLabel = $attributeData['attribute_store_label']
            ?? $attributeData['frontend_label']
            ?? self::$bucketMap[self::PRICE_BUCKET]['label'];

        $result = $this->layerFormatter->buildLayer(
            $attributeLabel,
            \count($bucket->getValues()),
            self::$bucketMap[self::PRICE_BUCKET]['request_name']
        );

        // buckets come back in base currency, so every boundary is converted before it is formatted
        $currencyRate = $this->storeManager->getStore()->getCurrentCurrencyRate();

        foreach ($bucket->getValues() as $value) {
            $metrics = $value->getMetrics();

            $priceRange = [
                'from' => $this->getMetricValue($metrics['from'], $currencyRate),
                'to' => $this->getMetricValue($metrics['to'], $currencyRate)
            ];

            $result['options'][] = $this->layerFormatter->buildItem(
                $priceRange['from'] . '~' . $priceRange['to'],
                $metrics['value'],
                $metrics['count']
            );
        }

        return [$result];
    }

    /**
     * converts price to the correct currency base, or a wildcard when unset
     * @param mixed $base
     * @param mixed $rate
     * @return float|int|string
     */
    private function getMetricValue($base, $rate)
    {
        return (is_numeric($base)) ? $base * $rate : '*';
    }

    /**
     * check that bucket contains data
     * @param BucketInterface|null $bucket
     * @return bool
     */
    private function isBucketEmpty(?BucketInterface $bucket): bool
    {
        return null === $bucket || !$bucket->getValues();
    }
}
