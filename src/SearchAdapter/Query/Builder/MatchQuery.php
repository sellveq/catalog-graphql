<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright © 2020 Scandiweb, Ltd (https://scandiweb.com)
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\SearchAdapter\Query\Builder;

use Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\AttributeProvider;
use Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\FieldProvider\FieldType\ResolverInterface as TypeResolver;
use Magento\Elasticsearch\Model\Adapter\FieldMapperInterface;
use Magento\Elasticsearch\Model\Config;
use Magento\Elasticsearch\SearchAdapter\Query\Builder\MatchQuery as CoreMatch;
use Magento\Elasticsearch\SearchAdapter\Query\ValueTransformerPool;
use Magento\Framework\Search\Request\Query\BoolExpression;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;

class MatchQuery extends CoreMatch
{
    /** Define fuzziness level of search query */
    public const string FUZZINESS_LEVEL = 'AUTO';

    /** Define unsupported match_condition types that do not support fuzziness field */
    protected const array UNSUPORTED_FUZZINESS_TYPES = ['match_phrase_prefix'];

    /**
     * @param FieldMapperInterface $fieldMapper
     * @param AttributeProvider $attributeProvider
     * @param TypeResolver $fieldTypeResolver
     * @param ValueTransformerPool $valueTransformerPool
     * @param Config $config
     */
    public function __construct(
        private readonly FieldMapperInterface $fieldMapper,
        private readonly AttributeProvider $attributeProvider,
        private readonly TypeResolver $fieldTypeResolver,
        private readonly ValueTransformerPool $valueTransformerPool,
        private readonly Config $config
    ) {
        parent::__construct(
            $fieldMapper,
            $attributeProvider,
            $fieldTypeResolver,
            $valueTransformerPool,
            $config
        );
    }

    /**
     * build ElasticSearch match-query conditions, with boost, quoted phrases and fuzziness
     * @param array $selectQuery
     * @param RequestQueryInterface $requestQuery
     * @param string $conditionType
     * @return array
     */
    public function build(array $selectQuery, RequestQueryInterface $requestQuery, $conditionType)
    {
        $queryValue = $this->prepareQuery($requestQuery->getValue(), $conditionType);
        $requestQueryBoost = $requestQuery->getBoost() ?: 1;
        $minimumShouldMatch = $this->config->getElasticsearchConfigData('minimum_should_match');

        // a value wrapped in escaped quotes asks for match_phrase, so the quotes are stripped first
        $count = 0;
        $value = preg_replace('#^"(.*)"$#m', '$1', $queryValue['value'], -1, $count);
        $condition = ($count) ? 'match_phrase' : 'match';
        $transformedTypes = [];

        foreach ($requestQuery->getMatches() as $match) {
            $resolvedField = $this->fieldMapper->getFieldName(
                $match['field'],
                ['type' => FieldMapperInterface::TYPE_QUERY]
            );
            $attributeAdapter = $this->attributeProvider->getByAttributeCode($resolvedField);
            $fieldType = $this->fieldTypeResolver->getFieldType($attributeAdapter);
            $valueTransformer = $this->valueTransformerPool->get($fieldType ?? 'text');
            $valueTransformerHash = \spl_object_hash($valueTransformer);

            if (!isset($transformedTypes[$valueTransformerHash])) {
                $transformedTypes[$valueTransformerHash] = $valueTransformer->transform($value);
            }
            $transformedValue = $transformedTypes[$valueTransformerHash];
            if (null === $transformedValue) {
                // the transformer returns null when the value cannot live in this field type
                continue;
            }

            $matchCondition = $match['matchCondition'] ?? $condition;
            $fields = [];
            $fields[$resolvedField] = [
                'query' => $transformedValue,
                'boost' => $requestQueryBoost + ($match['boost'] ?? 1),
            ];

            if (isset($match['analyzer'])) {
                $fields[$resolvedField]['analyzer'] = $match['analyzer'];
            }

            if (!in_array($matchCondition, self::UNSUPORTED_FUZZINESS_TYPES)) {
                $fields[$resolvedField]['fuzziness'] = self::FUZZINESS_LEVEL;
            }

            if ($minimumShouldMatch && $this->isConditionSupportMinimumShouldMatch($matchCondition)) {
                $fields[$resolvedField]['minimum_should_match'] = $minimumShouldMatch;
            }

            $selectQuery['bool'][$queryValue['condition']][] = [$matchCondition => $fields];
        }

        return $selectQuery;
    }

    /**
     * prepare query
     * @param string $queryValue
     * @param string $conditionType
     * @return array
     */
    private function prepareQuery(string $queryValue, string $conditionType): array
    {
        $condition = $conditionType === BoolExpression::QUERY_CONDITION_NOT
            ? CoreMatch::QUERY_CONDITION_MUST_NOT
            : $conditionType;

        return [
            'condition' => $condition,
            'value' => $queryValue,
        ];
    }

    /**
     * check does condition support the minimum_should_match field
     * @param string $condition
     * @return bool
     */
    private function isConditionSupportMinimumShouldMatch(string $condition): bool
    {
        return !in_array($condition, [
            'match_phrase_prefix',
            'match_phrase',
        ]);
    }
}
