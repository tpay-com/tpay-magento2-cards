<?php

/*
 * Created by tpay.com
 */

namespace tpaycom\magento2cards\lib;

/**
 * Class PaymentCard
 *
 * Class handles credit card payments through "Card API".
 * Depending on the chosen method:
 *  - client is redirected to card payment panel
 *  - card gate form is rendered
 *  - when user has saved card data only button is shown
 *
 * @package tpaycom
 */
class PaymentCard
{
    const RESULT = 'result';
    const ORDERID = 'order_id';
    const STRING = 'string';
    const SALE_AUTH = 'sale_auth';
    const REMOTE_ADDR = 'REMOTE_ADDR';
    /**
     * Merchant id
     * @var int
     */
    protected $merchantId = '[MERCHANT_ID]';

    /**
     * Merchant secret
     * @var string
     */
    private $merchantSecret = '[MERCHANT_SECRET]';

    /**
     * Card API key
     * @var string
     */
    private $apiKey = '[CARD_API_KEY]';

    /**
     * Card API password
     * @var string
     */
    private $apiPass = '[CARD_API_PASSWORD]';

    /**
     * Card API code
     * @var string
     */
    private $code = '[CARD_API_CODE]';

    /**
     * Card RSA key
     * @var string
     */
    private $keyRSA = '[CARD_RSA_KEY]';

    /**
     * Card hash algorithm
     * @var string
     */
    private $hashAlg = '[CARD_HASH_ALG]';

    /**
     * tpaycom response IP
     * @var string
     */
    private $secureIP = array(
        '176.119.38.175'
    );

    /**
     * If false library not validate tpaycom server IP
     * @var bool
     */
    private $validateServerIP = true;

    /**
     * PaymentCard class constructor for payment:
     * - card by panel
     * - card direct sale
     * - for saved cards
     *
     * @param string|bool $apiKey card api key
     * @param string|bool $apiPass card API password
     * @param string|bool $code card API code
     * @param string|bool $hashAlg card hash algorithm
     * @param string|bool $keyRSA card RSA key
     */
    public function __construct(
        $apiKey = false,
        $apiPass = false,
        $code = false,
        $hashAlg = false,
        $keyRSA = false
    ) {
        if ($apiKey !== false) {
            $this->apiKey = $apiKey;
        }
        if ($apiPass !== false) {
            $this->apiPass = $apiPass;
        }
        if ($code !== false) {
            $this->code = $code;
        }
        if ($hashAlg !== false) {
            $this->hashAlg = $hashAlg;
        }
        if ($keyRSA !== false) {
            $this->keyRSA = $keyRSA;
        }

        Validate::validateCardApiKey($this->apiKey);
        Validate::validateCardApiPassword($this->apiPass);
        Validate::validateCardCode($this->code);
        Validate::validateCardHashAlg($this->hashAlg);
        Validate::validateCardRSAKey($this->keyRSA);

    }

    /**
     * Disabling validation of payment notification server IP
     * Validation of tpaycom server ip is very important.
     * Use this method only in test mode and be sure to enable validation in production.
     */
    public function disableValidationServerIP()
    {
        $this->validateServerIP = false;
    }

    /**
     * Enabling validation of payment notification server IP
     */
    public function enableValidationServerIP()
    {
        $this->validateServerIP = true;
    }


    /**
     * Check cURL request from tpaycom server after payment.
     * This method check server ip, required fields and md5 checksum sent by payment server.
     * Display information to prevent sending repeated notifications.
     *
     * @return mixed
     *
     * @throws TException
     */
    public function handleNotification()
    {

        $notificationType = Util::post('type', static::STRING);
        if ($notificationType === 'sale') {
            $response = Validate::getResponse(Validate::PAYMENT_TYPE_CARD);
        } elseif ($notificationType === 'deregister') {
            $response = Validate::getResponse(Validate::CARD_DEREGISTER);
        } else {
            throw new TException('Unknown notification type');
        }

        if ($this->validateServerIP === true && $this->checkServer() === false) {
            throw new TException('Request is not from secure server');
        }

        echo json_encode(array(static::RESULT => '1'));

        if ($notificationType === 'sale' && $response[ResponseFields::STATUS] === 'correct') {

            $params = array(
                ResponseFields::ORDER_ID  => $response[ResponseFields::ORDER_ID],
                ResponseFields::SIGN      => $response[ResponseFields::SIGN],
                ResponseFields::SALE_AUTH => $response[ResponseFields::SALE_AUTH],
                ResponseFields::DATE      => $response[ResponseFields::DATE],
                ResponseFields::CARD      => $response[ResponseFields::CARD],
                ResponseFields::STATUS    => $response[ResponseFields::STATUS],
                ResponseFields::AMOUNT    => number_format(str_replace(array(',', ' '), array('.', ''),
                    $response[ResponseFields::AMOUNT]), 2,
                    '.', ''),
                ResponseFields::CURRENCY  => $response[ResponseFields::CURRENCY],
            );
            if (isset($response[ResponseFields::TEST_MODE])) {
                $params[ResponseFields::TEST_MODE] = $response[ResponseFields::TEST_MODE];
            }
            if (isset($response[ResponseFields::CLI_AUTH])) {
                $params[ResponseFields::CLI_AUTH] = $response[ResponseFields::CLI_AUTH];
            }
            return $params;
        } elseif ($notificationType === 'deregister') {
            return $response;
        } else {
            throw new TException('Incorrect payment');
        }
    }

    /**
     * Check if request is called from secure tpaycom server
     *
     * @return bool
     */
    private function checkServer()
    {
        if (!isset($_SERVER[static::REMOTE_ADDR])
            || !in_array($_SERVER[static::REMOTE_ADDR], $this->secureIP)
        ) {
            return false;
        }

        return true;
    }


    public function secureSale(
        $orderAmount,
        $orderID,
        $orderDesc,
        $currency = '985',
        $enablePowUrl = false,
        $language = 'pl',
        $powUrl = '',
        $powUrlBlad = ''
    ) {
        $cardData = Util::post('carddata', static::STRING);
        $clientName = Util::post('client_name', static::STRING);
        $clientEmail = Util::post('client_email', static::STRING);
        $saveCard = Util::post('card_save', static::STRING);

        $oneTimeTransaction = ($saveCard !== 'on');
        $amount = number_format(str_replace(array(',', ' '), array('.', ''), $orderAmount), 2, '.', '');
        $amount = (float)$amount;

        $api = new CardAPI($this->apiKey, $this->apiPass, $this->code, $this->hashAlg);

        $tmpConfig = array(
            'amount'         => $amount,
            'name'           => $clientName,
            'email'          => $clientEmail,
            'desc'           => $orderDesc,
            static::ORDERID  => $orderID,
            'enable_pow_url' => $enablePowUrl,
            'pow_url'        => $powUrl,
            'pow_url_blad'   => $powUrlBlad
        );


        Validate::validateConfig(Validate::PAYMENT_TYPE_CARD_DIRECT, $tmpConfig);
        $currency = Validate::validateCardCurrency($currency);


        $response = $api->secureSale(
            $clientName,
            $clientEmail,
            $orderDesc,
            $amount,
            $cardData,
            $currency,
            $orderID,
            $oneTimeTransaction,
            $language,
            $enablePowUrl,
            $powUrl,
            $powUrlBlad
        );


        return $response;
    }

    /**
     * Register sale for client saved card
     *
     * @param string $cliAuth client auth sign
     * @param string $saleAuth client sale sign
     *
     * @return bool|mixed
     */
    public function cardSavedSale($cliAuth, $saleAuth)
    {
        $api = new CardAPI($this->apiKey, $this->apiPass, $this->code, $this->hashAlg);

        return $api->sale($cliAuth, $saleAuth);
    }

    /**
     * Check md5 sum to validate tpaycom response.
     * The values of variables that md5 sum includes are available only for
     * merchant and tpaycom system.
     *
     * @param string $sign
     * @param string $testMode
     * @param string $saleAuth
     * @param string $orderId
     * @param string $card
     * @param float $amount
     * @param string $saleDate
     * @param string $currency
     *
     * @throws TException
     */
    public function validateSign($sign, $testMode, $saleAuth, $orderId, $card, $amount, $saleDate, $currency = '985')
    {
        if ($sign !== hash($this->hashAlg, 'sale' . $testMode . $saleAuth . $orderId . $card .
                $currency . $amount . $saleDate . 'correct' . $this->code)
        ) {
            throw new TException('Card payment - invalid checksum');
        }
    }

    /**
     * Check md5 sum to validate tpaycom response.
     * The values of variables that md5 sum includes are available only for
     * merchant and tpaycom system.
     *
     * @param string $sign
     * @param string $testMode
     * @param string $saleAuth
     * @param string $orderId
     * @param string $card
     * @param float $amount
     * @param string $saleDate
     * @param string $currency
     *
     * @throws TException
     */
    public function validateNon3dsSign(
        $sign,
        $testMode,
        $saleAuth,
        $orderId,
        $card,
        $amount,
        $saleDate,
        $currency = '985'
    ) {
        if ($sign !== hash($this->hashAlg, $testMode . $saleAuth . $orderId . $card .
                $currency . $amount . $saleDate . 'correct' . $this->code)
        ) {
            throw new TException('Card payment - invalid checksum');
        }
    }
}
