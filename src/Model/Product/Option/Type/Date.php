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

namespace ScandiPWA\CatalogGraphQl\Model\Product\Option\Type;

use Magento\CatalogGraphQl\Model\Product\Option\DateType as SourceDateType;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\Stdlib\DateTime;

class Date extends SourceDateType
{
    /**
     * {@inheritdoc}
     * @throws GraphQlInputException
     * @throws LocalizedException
     */
    public function validateUserValue($values)
    {
        if ($this->_dateExists() || $this->_timeExists()) {
            return parent::validateUserValue($this->formatValues($values));
        }

        return $this;
    }

    /**
     * format date value from string to date array
     * @param array $values
     * @return array
     * @throws GraphQlInputException
     * @throws LocalizedException
     */
    protected function formatValues($values)
    {
        if (isset($values[$this->getOption()->getId()])) {
            $value = $values[$this->getOption()->getId()];
            if (isset($value['date']) || isset($value['day'], $value['month'], $value['year'])) {
                return $values;
            }
            $dateTime = \DateTime::createFromFormat(DateTime::DATETIME_PHP_FORMAT, $value);

            if ($dateTime === false) {
                throw new GraphQlInputException(
                    __('Invalid format provided. Please use \'Y-m-d H:i:s\' format.')
                );
            }

            $values[$this->getOption()->getId()] = [
                'date' => $value,
                'year' => $dateTime->format('Y'),
                'month' => $dateTime->format('m'),
                'day' => $dateTime->format('d'),
                'hour' => $this->is24hTimeFormat()
                    ? $dateTime->format('H')
                    : $dateTime->format('h'),
                'minute' => $dateTime->format('i'),
                'day_part' => $dateTime->format('a'),
            ];
        }

        return $values;
    }
}
