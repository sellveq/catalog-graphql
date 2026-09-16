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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\Query;

use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ArgumentsProcessorInterface;
use Magento\Framework\GraphQl\Query\Uid;

class CategoryUidArgsProcessor implements ArgumentsProcessorInterface
{
    protected const string ID = 'category_id';

    protected const string UID = 'category_uid';

    /**
     * @param Uid $uidEncoder
     */
    public function __construct(
        private readonly Uid $uidEncoder
    ) {}

    /**
     * override to enable both category_id and category_uid to be used at the same time
     * @param string $fieldName
     * @param array $args
     * @return array
     * @throws GraphQlInputException
     */
    public function process(
        string $fieldName,
        array $args
    ): array {
        $idFilter = $args['filter'][self::ID] ?? [];
        $uidFilter = $args['filter'][self::UID] ?? [];

        if (empty($uidFilter)) {
            return $args;
        }

        if (isset($uidFilter['eq'])) {
            $args['filter'][self::ID]['eq'] = $this->uidEncoder->decode((string)$uidFilter['eq']);
        } elseif (!empty($uidFilter['in'])) {
            foreach ($uidFilter['in'] as $uid) {
                $args['filter'][self::ID]['in'][] = $this->uidEncoder->decode((string)$uid);
            }

            unset($args['filter'][self::ID]['eq']);
        }

        unset($args['filter'][self::UID]);

        return $args;
    }
}
