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

namespace ScandiPWA\CatalogGraphQl\Plugin\Resolver\Argument;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogWidget\Model\Rule;
use Magento\Framework\Data\Collection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Query\Resolver\Argument\AstConverter;
use Magento\Framework\GraphQl\Query\Resolver\Argument\Filter\ClauseFactory;
use Magento\Rule\Model\Condition\Combine;
use Magento\Rule\Model\Condition\Sql\Builder;
use Magento\Widget\Helper\Conditions;
use Psr\Log\LoggerInterface;

class AstConverterPlugin
{
    // every match is materialised into an sku IN (...) clause, so the rule engine needs a ceiling of its own
    public const int MAX_CONDITION_MATCHES = 500;

    private const string MATCH_LIMIT_GUARD = 'conditions_match_limit';

    // an empty IN list is dropped by the search request, so a rule matching nothing filters on an impossible sku
    private const string NO_MATCH_SKU = '';

    /**
     * @param Conditions $conditionsHelper
     * @param Rule $rule
     * @param ClauseFactory $clauseFactory
     * @param Builder $sqlBuilder
     * @param CollectionFactory $productCollectionFactory
     * @param Visibility $visibility
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Conditions $conditionsHelper,
        private readonly Rule $rule,
        private readonly ClauseFactory $clauseFactory,
        private readonly Builder $sqlBuilder,
        private readonly CollectionFactory $productCollectionFactory,
        private readonly Visibility $visibility,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * get conditions
     * @param mixed $conditions
     * @return Combine
     */
    protected function getConditions($conditions)
    {
        $conditions = $this->conditionsHelper->decode($conditions);

        foreach ($conditions as $key => $condition) {
            if (!empty($condition['attribute'])
                && in_array($condition['attribute'], ['special_from_date', 'special_to_date'])
            ) {
                $conditions[$key]['value'] = date('Y-m-d H:i:s', strtotime($condition['value']));
            }
        }

        $this->rule->loadPost(['conditions' => $conditions]);
        return $this->rule->getConditions();
    }

    /**
     * scoped as the products path scopes it, and bounded so one condition cannot materialise a catalogue
     * @param mixed $conditionValue
     * @return string[]
     * @throws LocalizedException
     */
    protected function loadProductSKUs($conditionValue): array
    {
        $conditionDecodedValue = base64_decode($conditionValue);
        $collection = $this->productCollectionFactory->create();
        $conditions = $this->getConditions($conditionDecodedValue);
        $conditions->collectValidatedAttributes($collection);
        $this->sqlBuilder->attachConditionToCollection($collection, $conditions);
        $collection->addAttributeToSelect('sku')
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->setVisibility($this->visibility->getVisibleInCatalogIds())
            ->addStoreFilter()
            ->setOrder('entity_id', Collection::SORT_ORDER_ASC)
            ->setPageSize(self::MAX_CONDITION_MATCHES);

        $matchCount = $collection->getSize();

        // the cut is by entity_id, so the same tree keeps answering with the same products
        if ($matchCount > self::MAX_CONDITION_MATCHES) {
            $this->logger->warning(sprintf(
                '%s: limit %d, matched %d, query proceeds on the first %d by entity_id',
                self::MATCH_LIMIT_GUARD,
                self::MAX_CONDITION_MATCHES,
                $matchCount,
                self::MAX_CONDITION_MATCHES
            ));
        }

        $SKUs = [];
        foreach ($collection->getItems() as $item) {
            $SKUs[] = $item->getSku();
        }

        return $SKUs;
    }

    /**
     * @param AstConverter $subject
     * @param callable $next
     * @param string $fieldName
     * @param array $arguments
     * @return array
     * @throws LocalizedException
     */
    public function aroundGetClausesFromAst(
        AstConverter $subject,
        callable $next,
        string $fieldName,
        array $arguments
    ): array {
        if (!array_key_exists('conditions', $arguments)) {
            return $next($fieldName, $arguments);
        }

        $conditionArgument = $arguments['conditions'];
        $conditionArgumentType = array_key_first($conditionArgument);

        if ($conditionArgumentType !== 'eq') {
            throw new LocalizedException(__("'conditions' field only supports 'eq' condition type."));
        }

        $SKUs = $this->loadProductSKUs($conditionArgument[$conditionArgumentType]);
        // the clause replaces the field, so it must not also travel on as a product attribute filter
        unset($arguments['conditions']);

        $conditions = $next($fieldName, $arguments);
        $conditions[] = $this->clauseFactory->create(
            'sku',
            'in',
            $SKUs ?: [self::NO_MATCH_SKU]
        );

        return $conditions;
    }
}
