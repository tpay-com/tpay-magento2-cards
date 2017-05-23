<?php

/**
 * @category    payment gateway
 * @package     tpaycom_magento2cards
 * @author      tpay.com
 * @copyright   (https://tpay.com)
 */

namespace tpaycom\magento2cards\lib;

/**
 * Class ResponseFields
 *
 * @package tpaycom\magento2cards\lib
 */
class ResponseFields
{
    const TYPE = 'type';
    const TEST_MODE = 'test_mode';
    const SALE_AUTH = 'sale_auth';
    const ORDER_ID = 'order_id';
    const CLI_AUTH = 'cli_auth';
    const CARD = 'card';
    const CURRENCY = 'currency';
    const AMOUNT = 'amount';
    const STATUS = 'status';
    const SIGN = 'sign';
    const DATE = 'date';
    const REASON = 'reason';
    const URL3DS = '3ds_url';
}
