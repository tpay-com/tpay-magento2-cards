<?php
/**
 * Created by tpay.com.
 * Date: 25.08.2017
 * Time: 16:11
 */

namespace tpaycom\magento2cards\Model;


use tpaycom\magento2cards\Api\TpayCardsInterface;
use tpaycom\magento2cards\lib\CardAPI;
use tpaycom\magento2cards\lib\PaymentCardFactory;

class ApiProvider
{
    /**
     * @var TpayCardsInterface
     */
    private $tpay;

    private $paymentCardFactory;

    public function __construct(TpayCardsInterface $tpayModel, PaymentCardFactory $paymentCardFactory)
    {
        $this->tpay = $tpayModel;
        $this->paymentCardFactory = $paymentCardFactory;
        $this->apiPass = $this->tpay->getApiPassword();
        $this->apiKey = $this->tpay->getApiKey();
        $this->verificationCode = $this->tpay->getVerificationCode();
        $this->hashAlg = $this->tpay->getHashType();
        $this->RSAKey = $this->tpay->getRSAKey();
    }
    public function getTpayPaymentCardFactory()
    {
        return $this->paymentCardFactory->create([
            'apiKey'  => $this->apiKey,
            'apiPass' => $this->apiPass,
            'code'    => $this->verificationCode,
            'hashAlg' => $this->hashAlg,
            'keyRSA'  => $this->RSAKey
        ]);
    }
    public function getTpayCardAPI()
    {
        return new CardAPI($this->apiKey, $this->apiPass, $this->verificationCode, $this->hashAlg);
    }
}
