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

use Exception;
use Magento\Downloadable\Model\LinkFactory;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class SampleUrl implements ResolverInterface
{
    /**
     * @var LinkFactory
     */
    protected $_linkFactory;

    /**
     * @param LinkFactory $linkRepository
     */
    public function __construct(
        LinkFactory $linkRepository
    ) {
        $this->_linkFactory = $linkRepository;
    }

    /**
     * fetches the data from persistence models and format it according to the GraphQL schema.
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @throws Exception
     * @return mixed|Value
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $linkId = $value['id'];

        $link = $this->_linkFactory->create()->load($linkId);

        return ($link->getSampleFile() || $link->getSampleUrl()) ? $value['sample_url'] : '';
    }
}
