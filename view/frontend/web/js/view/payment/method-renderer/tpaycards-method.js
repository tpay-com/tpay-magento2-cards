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
                $("#card_number").val('');
                $("#cvc").val('');
                $("#expiry_date").val('');
                $("#loading_scr").fadeIn();
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
                var savedId = 'new';
                $('input[id^=cardN]').each(function () {
                    if ($(this).is(":checked")) {
                        savedId = $(this).val();
                    }
                });
                var parent = this._super(),
                    paymentData = {};
                paymentData['card_data'] = $('input[name="card_data"]').val();
                paymentData['card_save'] = $('input[name="card_save"]').is(":checked");
                paymentData['card_id'] = savedId;
                paymentData['card_vendor'] = $('input[name="card_vendor"]').val();

                return $.extend(true, parent, {'additional_data': paymentData});
            },
            showSaveBox: function () {
                if (window.checkoutConfig.tpaycards.payment.isCustomerLoggedIn) {
                    $('.amPmCheckbox').css('display', 'block');
                }
            },
            isActive: function () {
                return true;
            }

        });
    });
