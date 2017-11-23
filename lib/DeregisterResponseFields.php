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
class DeregisterResponseFields
{
    const TYPE = 'type';
    const TEST_MODE = 'test_mode';
    const CLI_AUTH = 'cli_auth';
    const SIGN = 'sign';
    const DATE = 'date';
}
