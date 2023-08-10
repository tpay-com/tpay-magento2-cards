<?php

namespace tpaycom\magento2cards\Model;

use tpayLibs\src\_class_tpay\Refunds\CardRefunds;

class CardRefundModel extends CardRefunds
{
    /**
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
