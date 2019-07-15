<?php
/**
 *
 * @category    payment gateway
 * @package     Tpaycom_Magento2.3
 * @author      Tpay.com
 * @copyright   (https://tpay.com)
 */

namespace tpaycom\magento2cards\Model;

use tpayLibs\src\_class_tpay\Refunds\CardRefunds;

/**
 * Class CardRefund
 *
 * @package tpaycom\magento2cards\Model
 */
class CardRefundModel extends CardRefunds
{
    /**
     * Transaction constructor.
     *
     * @param string $apiPassword
     * @param string $apiKey
     * @param string $verificationCode
     * @param string $keyRsa
     * @param string $hashType
     */
    public function __construct($apiPassword, $apiKey, $verificationCode, $keyRsa, $hashType)
    {
        $this->cardApiKey = $apiKey;
        $this->cardApiPass = $apiPassword;
        $this->cardVerificationCode = $verificationCode;
        $this->cardKeyRSA = $keyRsa;
        $this->cardHashAlg = $hashType;
        parent::__construct();
    }

}
