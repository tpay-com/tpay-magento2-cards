/**
 *
 * @category    payment gateway
 * @package     Tpaycom_Magento2.1
 * @author      Tpay.com
 * @copyright   (https://tpay.com)
 *//*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component,
              rendererList) {
        'use strict';
        rendererList.push(
            {
                type: 'tpaycom_magento2cards',
                component: 'tpaycom_magento2cards/js/view/payment/method-renderer/tpaycards-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
