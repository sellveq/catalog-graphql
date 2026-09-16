<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright © 2022 Scandiweb, Ltd (https://scandiweb.com)
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Helper\Category as CategoryHelper;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\StateDependentCollectionFactory;
use Magento\Framework\Data\Collection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;

class MenuItems implements ResolverInterface
{
    /**
     * @var StateDependentCollectionFactory
     */
    protected $collectionFactory;

    /**
     * @param CategoryHelper $catalogCategory
     * @param StateDependentCollectionFactory $categoryCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        private readonly CategoryHelper $catalogCategory,
        StateDependentCollectionFactory $categoryCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {
        $this->collectionFactory = $categoryCollectionFactory;
    }

    /**
     * standard Magento menu logic, as in Magento\Catalog\Plugin\Block\Topmenu
     * {@inheritdoc}
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $rootId = $this->storeManager->getStore()->getRootCategoryId();
        $storeId = $this->storeManager->getStore()->getId();

        $collection = $this->getCategoryTree($storeId, $rootId);
        // the storefront menu expects a root row the category tree does not carry
        $mapping = [
            [
                'category_id' => 0,
                'item_id' => $rootId,
                'parent_id' => 0,
                'title' => '',
                'url' => '/'
            ]
        ];

        foreach ($collection as $category) {
            $categoryParentId = $category->getParentId();
            if (!isset($mapping[$categoryParentId])) {
                $parentIds = $category->getParentIds();
                foreach ($parentIds as $parentId) {
                    if (isset($mapping[$parentId])) {
                        $categoryParentId = $parentId;
                    }
                }
            }

            $categoryArray = $this->getCategoryAsArray($category);
            $mapping[$category->getId()] = $categoryArray;
        }

        return array_values($mapping);
    }

    /**
     * get Category Tree
     * @param int $storeId
     * @param int $rootId
     * @return CategoryCollection
     * @throws LocalizedException
     */
    protected function getCategoryTree($storeId, $rootId)
    {
        /** @var CategoryCollection $collection */
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect('name');
        // load only from store root
        $collection->addFieldToFilter('path', ['like' => '1/' . $rootId . '/%']);
        $collection->addAttributeToFilter('include_in_menu', 1);
        $collection->addIsActiveFilter();
        $collection->addNavigationMaxDepthFilter();
        $collection->addUrlRewriteToResult();
        $collection->addOrder('level', Collection::SORT_ORDER_ASC);
        $collection->addOrder('position', Collection::SORT_ORDER_ASC);
        $collection->addOrder('parent_id', Collection::SORT_ORDER_ASC);
        $collection->addOrder('entity_id', Collection::SORT_ORDER_ASC);

        return $collection;
    }

    /**
     * convert category to array
     * @param Category $category
     * @return array
     * @throws NoSuchEntityException
     */
    protected function getCategoryAsArray($category)
    {
        return [
            'title' => $category->getName(),
            'item_id' => $category->getId(),
            'category_id' => $category->getId(),
            'url' => $this->catalogCategory->getCategoryUrl($category),
            'parent_id' => $category->getParentId(),
            'position' => $category->getPosition(),
            // a category never saved with a display mode returns null, not the default
            'display_mode' => $this->getCategoryDisplayMode($category) ?? Category::DM_PRODUCT
        ];
    }

    /**
     * get category display mode, which a collection-loaded category lacks
     * @param Category $categoryData
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getCategoryDisplayMode($categoryData)
    {
        $category = $this->categoryRepository->get($categoryData->getId());

        return $category->getDisplayMode();
    }
}
