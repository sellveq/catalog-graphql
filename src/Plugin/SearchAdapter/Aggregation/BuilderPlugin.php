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

namespace ScandiPWA\CatalogGraphQl\Plugin\SearchAdapter\Aggregation;

use Magento\Elasticsearch\SearchAdapter\Aggregation\Builder;
use Magento\Framework\Search\Request\Aggregation\DynamicBucket;
use Magento\Framework\Search\RequestInterface;
use ScandiPWA\CatalogGraphQl\Model\Layer\Filter\Price;

class BuilderPlugin
{
    /**
     * keeps the price bucket that Magento drops when the algorithm generates only one range
     * @param Builder $subject
     * @param mixed $result
     * @param RequestInterface $request
     * @return mixed
     */
    public function afterBuild(Builder $subject, $result, RequestInterface $request)
    {
        // only the improved algorithm collapses to a single range, so nothing else needs repairing
        $requestPriceBucket = $this->getPriceAggregationRequestBucket($request);

        if (!is_null($requestPriceBucket) && $requestPriceBucket->getMethod() == 'improved') {
            $resultPriceBucket = $result[Price::PRICE_BUCKET] ?? null;

            if ($resultPriceBucket && count($resultPriceBucket) == 1) {
                // without an applied price filter the single range is genuine, not a collapse
                $priceFilter = $request->getQuery()->getMust()['price'] ?? null;

                if ($priceFilter) {
                    $from = $priceFilter->getReference()->getFrom();
                    $to = $priceFilter->getReference()->getTo();

                    // no upper limit means the last interval; otherwise offset the later subtraction
                    $to = is_null($to) ? '*' : $to + 0.01;
                    $count = array_values($resultPriceBucket)[0]['count'];

                    $result[Price::PRICE_BUCKET] = [
                        $from . '_' . $to => [
                            'from' => $from,
                            'to' => $to,
                            'count' => $count,
                            'value' => $from . '_' . $to
                        ]
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * @param RequestInterface $request
     * @return DynamicBucket|null
     */
    protected function getPriceAggregationRequestBucket($request)
    {
        foreach ($request->getAggregation() as $bucket) {
            if ($bucket->getName() == Price::PRICE_BUCKET) {
                return $bucket;
            }
        }

        return null;
    }
}
