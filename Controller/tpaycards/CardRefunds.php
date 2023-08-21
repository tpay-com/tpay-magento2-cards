<?php

namespace tpaycom\magento2cards\Controller\tpaycards;

use Magento\Framework\Validator\Exception;
use Magento\Sales\Model\Order\Payment;
use tpaycom\magento2cards\Model\CardRefundModel;
use tpayLibs\src\_class_tpay\Utilities\Util;

class CardRefunds
{
    private $apiKey;
    private $apiPass;
    private $verificationCode;
    private $hashType;
    private $keyRsa;

    /**
     * @param string $apiKey
     * @param string $apiPass
     * @param string $verificationCode
     * @param string $keyRsa
     * @param string $hashType
     *
     * @internal param $verifCode
     */
    public function __construct($apiKey, $apiPass, $verificationCode, $keyRsa, $hashType)
    {
        $this->apiKey = $apiKey;
        $this->apiPass = $apiPass;
        $this->verificationCode = $verificationCode;
        $this->keyRsa = $keyRsa;
        $this->hashType = $hashType;
        Util::$loggingEnabled = false;
    }

    /**
     * @param Payment     $payment
     * @param float       $amount
     * @param null|string $currency
     *
     * @throws Exception
     *
     * @return bool
     */
    public function makeRefund($payment, $amount, $currency = '985')
    {
        $tpayApi = new CardRefundModel(
            $this->apiPass,
            $this->apiKey,
            $this->verificationCode,
            $this->keyRsa,
            $this->hashType
        );
        $transactionId = $payment->getParentTransactionId();
        $tpayApi->setAmount($amount)->setCurrency($currency);
        $result = $tpayApi->refund($transactionId, __('Zwrot do zamówienia ').$payment->getOrder()->getRealOrderId());

        if (1 === (int) $result['result'] && isset($result['status']) && 'correct' === $result['status']) {
            return $result['sale_auth'];
        }
        $errDesc = isset($result['err_desc']) ? ' error description: '.$result['err_desc'] : '';
        $errCode = isset($result['err_code']) ? ' error code: '.$result['err_code'] : '';
        $reason = isset($result['reason']) ? ' reason: '.$result['reason'] : '';
        throw new Exception(__('Payment refunding error. -'.$errCode.$errDesc.$reason));
    }
}
