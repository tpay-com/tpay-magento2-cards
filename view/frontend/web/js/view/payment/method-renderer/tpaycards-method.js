/**
 *
 * @category    payment gateway
 * @package     Tpaycom_Magento2.1
 * @author      Tpay.com
 * @copyright   (https://tpay.com)
 */
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'jquery'
    ],
    function (Component, $) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'tpaycom_magento2cards/payment/tpaycards-form'
            },

            redirectAfterPlaceOrder: false,

            getCode: function () {
                return 'tpaycom_magento2cards';
            },
            afterPlaceOrder: function () {
                window.location.replace(window.checkoutConfig.tpaycards.payment.redirectUrl);
            },
            fetchJavaScripts: function () {
                return window.checkoutConfig.tpaycards.payment.fetchJavaScripts;
            },

            getRSAkey: function () {
                return window.checkoutConfig.tpaycards.payment.getRSAkey;
            },
            getLogoUrl: function () {
                return window.checkoutConfig.tpaycards.payment.tpayLogoUrl;
            },
            getTpayLoadingGif: function () {
                return window.checkoutConfig.tpaycards.payment.getTpayLoadingGif;
            },
            addCSS: function () {
                return window.checkoutConfig.tpaycards.payment.addCSS;
            },
            getData: function () {
                var parent = this._super(),
                    paymentData = {};
                paymentData['card_data'] = $('input[name="card_data"]').val();
                paymentData['c_name'] = $('input[name="client_name"]').val();
                paymentData['c_email'] = $('input[name="client_email"]').val();
                return $.extend(true, parent, {'additional_data': paymentData});
            },

            isActive: function () {
                return true;
            }
            ,


        })
            ;
    }
)
;
