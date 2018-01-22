<?php

namespace tpaycom\magento2cards\Controller\tpaycards;

use Magento\Sales\Model\Order\Payment;
use tpaycom\magento2cards\lib\CardAPI;
use Magento\Framework\Validator\Exception;

class CardRefunds
{
    private $apiKey;
    private $apiPass;
    private $verificationCode;
    private $hashType;

    /**
     * Refunds constructor.
     * @param $apiKey
     * @param $apiPass
     * @param $verificationCode
     * @param $hashType
     * @internal param $verifCode
     */

    public function __construct(
        $apiKey,
        $apiPass,
        $verificationCode,
        $hashType
    ) {
        $this->apiKey = $apiKey;
        $this->apiPass = $apiPass;
        $this->verificationCode = $verificationCode;
        $this->hashType = $hashType;
    }

    /**
     * @param Payment $payment
     * @param double $amount
     * @return bool
     * @throws Exception
     */
    public function makeRefund($payment, $amount)
    {
        $tpayApi = new CardAPI($this->apiKey, $this->apiPass, $this->verificationCode, $this->hashType);
        $transactionId = $payment->getParentTransactionId();

        $result = $tpayApi->refund($transactionId, 'Zwrot do zamówienia ' . $payment->getOrder()->getRealOrderId(),
            $amount);

        if ((int)$result['result'] === 1 && isset($result['status']) && $result['status'] === 'correct') {
            return $result['sale_auth'];
        } else {
            $errDesc = isset($result['err_desc']) ? ' error description: ' . $result['err_desc'] : '';
            $errCode = isset($result['err_code']) ? ' error code: ' . $result['err_code'] : '';
            $reason = isset($result['reason']) ? ' reason: ' . $result['reason'] : '';
            throw new Exception(__('Payment refunding error. -' . $errCode . $errDesc . $reason));
        }

    }

}
