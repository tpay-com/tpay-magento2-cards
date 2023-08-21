<?php

namespace tpaycom\magento2cards\Api;

/**
 * @api
 */
interface TpayCardsInterface
{
    const CODE = 'tpaycom_magento2cards';
    const CARDDATA = 'card_data';
    const CARD_SAVE = 'card_save';
    const CARD_ID = 'card_id';
    const CARD_VENDOR = 'card_vendor';

    /**
     * Return data for form
     *
     * @param null|int $orderId
     *
     * @return array
     */
    public function getTpayFormData($orderId = null);

    /** @return string */
    public function getApiPassword();

    /** @return string */
    public function getApiKey();

    /** @return string */
    public function getVerificationCode();

    /** @return string */
    public function getRSAKey();

    /** @return string */
    public function getHashType();

    /** @return string */
    public function getMidType();

    /**
     * Return url to redirect after placed order
     *
     * @return string
     */
    public function getPaymentRedirectUrl();

    /**
     * Check if send an email about the new invoice to customer
     *
     * @return string
     */
    public function getInvoiceSendMail();

    /**
     * @param mixed $orderId
     *
     * @return string
     */
    public function getCustomerId($orderId);

    /**
     * @param mixed $orderId
     *
     * @return bool
     */
    public function isCustomerGuest($orderId);

    /** @return bool */
    public function isCustomerLoggedIn();

    /** @return null|int */
    public function getCheckoutCustomerId();

    /** @return bool */
    public function getCardSaveEnabled();
}
