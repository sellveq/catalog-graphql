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

namespace ScandiPWA\CatalogGraphQl\Elasticsearch5\SearchAdapter\Aggregation;

use Magento\CatalogSearch\Model\Indexer\Fulltext;
use Magento\Elasticsearch\Elasticsearch5\SearchAdapter\Aggregation\Interval as CoreInterval;
use Magento\Elasticsearch\Model\Adapter\FieldMapperInterface;
use Magento\Elasticsearch\Model\Config;
use Magento\Elasticsearch\SearchAdapter\ConnectionManager;
use Magento\Elasticsearch\SearchAdapter\SearchIndexNameResolver;

class Interval extends CoreInterval
{
    /** Minimal possible value */
    public const float DELTA = 0.005;

    /**
     * @param ConnectionManager $connectionManager
     * @param FieldMapperInterface $fieldMapper
     * @param Config $clientConfig
     * @param SearchIndexNameResolver $searchIndexNameResolver
     * @param string $fieldName
     * @param string $storeId
     * @param array $entityIds
     */
    public function __construct(
        private readonly ConnectionManager $connectionManager,
        FieldMapperInterface $fieldMapper,
        private readonly Config $clientConfig,
        private readonly SearchIndexNameResolver $searchIndexNameResolver,
        private readonly string $fieldName,
        private readonly string $storeId,
        private readonly array $entityIds
    ) {
        parent::__construct(
            $connectionManager,
            $fieldMapper,
            $clientConfig,
            $searchIndexNameResolver,
            $fieldName,
            $storeId,
            $entityIds
        );
    }

    /**
     * {@inheritdoc}
     */
    public function load($limit, $offset = null, $lower = null, $upper = null)
    {
        // the bounds are optional on this call, so both sides start at a range the engine accepts
        $from = ['gte' => 0];
        $to = ['lt' => 0];

        if ($lower) {
            $from = ['gte' => $lower - self::DELTA];
        }

        if ($upper) {
            $to = ['lt' => $upper - self::DELTA];
        }

        $requestQuery = $this->prepareBaseRequestQuery($from, $to);
        $requestQuery = array_merge_recursive(
            $requestQuery,
            ['body' => ['stored_fields' => [$this->fieldName], 'size' => $limit]]
        );

        if ($offset) {
            $requestQuery['body']['from'] = $offset;
        }

        $queryResult = $this->connectionManager->getConnection()
            ->query($requestQuery);

        return $this->arrayValuesToFloat($queryResult['hits']['hits'], $this->fieldName);
    }

    /**
     * {@inheritdoc}
     */
    public function loadPrevious($data, $index, $lower = null)
    {
        // the bounds are optional on this call, so both sides start at a range the engine accepts
        $from = ['gte' => 0];
        $to = ['lt' => 0];

        if ($lower) {
            $from = ['gte' => $lower - self::DELTA];
        }
        if ($data) {
            $to = ['lt' => $data - self::DELTA];
        }

        $requestQuery = $this->prepareBaseRequestQuery($from, $to);
        $requestQuery = array_merge_recursive(
            $requestQuery,
            ['size' => 0]
        );

        $queryResult = $this->connectionManager->getConnection()
            ->query($requestQuery);

        $offset = $queryResult['hits']['total'];
        if (!$offset) {
            return false;
        }

        if (is_array($offset)) {
            $offset = $offset['value'];
        }

        return $this->load($index - $offset + 1, $offset - 1, $lower);
    }

    /**
     * conver array values to float type.
     * @param array $hits
     * @param string $fieldName
     * @return float[]
     */
    protected function arrayValuesToFloat(array $hits, string $fieldName): array
    {
        $returnPrices = [];
        foreach ($hits as $hit) {
            $returnPrices[] = (float)$hit['fields'][$fieldName][0];
        }

        return $returnPrices;
    }

    /**
     * prepare base query for search.
     * @param array|null $from
     * @param array|null $to
     * @return array
     */
    protected function prepareBaseRequestQuery($from = null, $to = null): array
    {
        $requestQuery = [
            'index' => $this->searchIndexNameResolver->getIndexName($this->storeId, Fulltext::INDEXER_ID),
            'type' => $this->clientConfig->getEntityType(),
            'body' => [
                'stored_fields' => [
                    '_id',
                ],
                'query' => [
                    'bool' => [
                        'must' => [
                            'match_all' => new \stdClass(),
                        ],
                        'filter' => [
                            'bool' => [
                                'must' => [
                                    [
                                        'terms' => [
                                            '_id' => $this->entityIds,
                                        ],
                                    ],
                                    [
                                        'range' => [
                                            $this->fieldName => array_merge($from, $to),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'sort' => [
                    $this->fieldName,
                ],
            ],
        ];

        return $requestQuery;
    }
}
