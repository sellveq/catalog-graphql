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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Category;

use Magento\Catalog\Model\Category\Attribute\Source\Sortby;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\Config;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class SortFields implements ResolverInterface
{
    /**
     * @param Config $catalogConfig
     * @param Sortby $sortbyAttributeSource
     * @param CategoryRepository $categoryRepository
     */
    public function __construct(
        private readonly Config $catalogConfig,
        private readonly Sortby $sortbyAttributeSource,
        private readonly CategoryRepository $categoryRepository
    ) {}

    /**
     * {@inheritdoc}
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        return [
            'default' => $this->getDefaultSortOption($context),
            'options' => $this->getSortOptions($context)
        ];
    }

    /**
     * @param ContextInterface $context
     * @return array
     * @throws NoSuchEntityException
     */
    private function getSortOptions($context): array
    {
        $categoryId = $this->getCategoryId($context);
        $sortOptions = [];

        if ($categoryId) {
            $sortOptions = $this->getSortOptionsByCategory($categoryId);
        }

        if (!count($sortOptions)) {
            $sortOptions = $this->sortbyAttributeSource->getAllOptions();
        }

        array_walk(
            $sortOptions,
            function (&$option) {
                $option['label'] = (string)$option['label'];
            }
        );

        return $sortOptions;
    }

    /**
     * @param ContextInterface $context
     * @return int
     */
    private function getCategoryId($context): int
    {
        $categoryId = 0;
        $filterGroups = $context->getExtensionAttributes()->getSearchCriteria()->getFilterGroups();

        foreach ($filterGroups as $filterGroup) {
            $filters = $filterGroup->getFilters();

            foreach ($filters as $filter) {
                $field = $filter->getField();

                if ($field === 'category_id') {
                    $categoryId = (int)$filter->getValue();
                }
            }
        }

        return $categoryId;
    }

    /**
     * @param ContextInterface $context
     * @return string
     */
    private function getDefaultSortOption($context): string
    {
        return $this->catalogConfig->getProductListDefaultSortBy(
            (int)$context->getExtensionAttributes()->getStore()->getId()
        );
    }

    /**
     * @param int $categoryId
     * @return array
     * @throws NoSuchEntityException
     */
    private function getSortOptionsByCategory(int $categoryId): array
    {
        $result = [];
        $category = $this->categoryRepository->get($categoryId);
        $sortBy = $category->getAvailableSortBy() ?? [];

        foreach ($sortBy as $sortItem) {
            $result[] = [
                'value' => $sortItem,
                'label' => $this->sortbyAttributeSource->getOptionText($sortItem)
            ];
        }

        return $result;
    }
}
