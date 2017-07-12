<?php
/**
 *
 * @category    payment gateway
 * @package     Tpaycom_Magento2.1
 * @author      tpay.com
 * @copyright   (https://tpay.com)
 */

namespace tpaycom\magento2cards\Api;

/**
 * Interface TpayCardsInterface
 *
 * @package tpaycom\magento2cards\Api
 * @api
 */
interface TpayCardsInterface
{
    /**
     * @var string
     */
    const CODE = 'tpaycom_magento2cards';
    /**
     * @var string
     */
    const CARDDATA = 'card_data';


    /**
     * Return data for form
     *
     * @param null|int $orderId
     *
     * @return array
     */
    public function getTpayFormData($orderId = null);

    /**
     * @return string
     */
    public function getApiPassword();

    /**
     * @return string
     */
    public function getApiKey();
    /**
     * @return string
     */
    public function getVerificationCode();
    /**
     * @return string
     */
    public function getRSAKey();
    /**
     * @return string
     */
    public function getHashType();
    /**
     * @return string
     */
    public function getMidType();
    /**
     * Return url to redirect after placed order
     *
     * @return string
     */
    public function getPaymentRedirectUrl();
}
