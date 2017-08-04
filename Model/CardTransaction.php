<?php
/**
 *
 * @category    payment gateway
 * @package     Tpaycom_Magento2.1
 * @author      tpay.com
 * @copyright   (https://tpay.com)
 */

namespace tpaycom\magento2cards\Model;

use tpaycom\magento2cards\lib\Curl;

/**
 * Class CardTransaction
 *
 * @package tpaycom\magento2cards\Model
 */
class CardTransaction
{

    /**
     * API tpay.com url
     *
     * @var string
     */
    private $urlApi = 'https://secure.tpay.com/api/cards';
    /**
     * API password
     *
     * @var  string
     */
    private $apiPassword;

    /**
     * API key
     *
     * @var string
     */
    private $apiKey;

    /**
     * CardTransaction constructor.
     *
     * @param string $apiPassword
     * @param string $apiKey
     */
    public function __construct($apiPassword, $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->apiPassword = $apiPassword;
    }

    /**
     * Send BLIK code for a generated transaction
     *
     * @param $transactionData
     *
     * @return array
     */
    public function createCardTransaction($transactionData)
    {
        $url = "{$this->urlApi}/{$this->apiKey}";
        $transactionData['json'] = 1;

        return (array)json_decode(Curl::doCurlRequest($url, $transactionData));

    }
}
