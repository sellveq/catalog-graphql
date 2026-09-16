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

namespace ScandiPWA\CatalogGraphQl\Search\Dynamic\Algorithm;

use Magento\Framework\Search\Adapter\OptionsInterface;
use Magento\Framework\Search\Dynamic\Algorithm;
use Magento\Framework\Search\Dynamic\Algorithm\AlgorithmInterface;
use Magento\Framework\Search\Dynamic\DataProviderInterface;
use Magento\Framework\Search\Dynamic\EntityStorage;
use Magento\Framework\Search\Request\BucketInterface;

class Improved implements AlgorithmInterface
{
    /**
     * @param DataProviderInterface $dataProvider
     * @param Algorithm $algorithm
     * @param OptionsInterface $options
     */
    public function __construct(
        private readonly DataProviderInterface $dataProvider,
        private readonly Algorithm $algorithm,
        private readonly OptionsInterface $options
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getItems(
        BucketInterface $bucket,
        array $dimensions,
        EntityStorage $entityStorage
    ) {
        $aggregations = $this->dataProvider->getAggregations($entityStorage);

        $options = $this->options->get();
        if ($aggregations['count'] < $options['interval_division_limit']) {
            return [[
                'from' => $aggregations['min'],
                'to' => $aggregations['max'],
                'count' => $aggregations['count']
            ]];
        }
        $this->algorithm->setStatistics(
            $aggregations['min'],
            $aggregations['max'],
            $aggregations['std'],
            $aggregations['count']
        );

        $this->algorithm->setLimits($aggregations['min'], $aggregations['max']);

        $interval = $this->dataProvider->getInterval($bucket, $dimensions, $entityStorage);
        $data = $this->algorithm->calculateSeparators($interval);

        $data[0]['from'] = 0;

        foreach (array_keys($data) as $key) {
            if (isset($data[$key + 1])) {
                $data[$key]['to'] = $data[$key + 1]['from'];
            }
        }

        return $data;
    }
}
