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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Currency;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;

class CurrencyResolver implements ResolverInterface
{
    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {}

    /**
     * {@inheritdoc}
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        return [
            'available_currencies_data' => $this->getCurrenciesData(),
            'current_currency_code' => $this->storeManager->getStore()->getCurrentCurrency()->getCode()
        ];
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getCurrenciesData()
    {
        $availableCurrenciesCodes = $this->storeManager->getStore()->getAvailableCurrencyCodes();
        $currencyData = [];

        foreach ($availableCurrenciesCodes as $currencyCode) {
            $currencyData[] = [
                'id' => $currencyCode,
                'label' => $currencyCode,
                'value' => $currencyCode
            ];
        }

        return $currencyData;
    }
}
