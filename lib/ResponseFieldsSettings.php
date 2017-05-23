<?php
/**
 * Created by tpay.com.
 * Date: 26.04.2017
 * Time: 17:43
 */

namespace tpaycom\magento2cards\lib;


class ResponseFieldsSettings
{
    static $fields = array(
        /**
         * Method type
         */
        FieldProperties::TYPE => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::OPTIONS),
            FieldProperties::OPTIONS    => array('sale', 'refund', 'deregister'),
        ),
        /**
         * Merchant optional value
         */
        'order_id'            => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING, 'maxlength__40')
        ),
        /**
         * Payment status
         */
        'status'              => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::OPTIONS),
            FieldProperties::OPTIONS    => array('correct', 'declined'),
        ),
        /**
         * Message checksum
         */
        'sign'                => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING, 'maxlength_128', 'maxlength_40')
        ),
        /**
         * Created sale/refund id
         */
        'sale_auth'           => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING, 'maxlength_40')
        ),
        /**
         * Date of accounting/deregistering
         */
        'date'                => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING)
        ),
        /**
         * carry value of 1 if account has test mode, otherwise parameter not sent
         */
        'test_mode'           => array(
            FieldProperties::REQUIRED   => false,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING, 'maxlength_1', 'minlength_1')
        ),
        /**
         * shortcut for client card number, eg ****5678
         */
        'card'                => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING, 'maxlength_8', 'minlength_8')
        ),
        /**
         * transaction amount
         */
        'amount'              => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::FLOAT,
            FieldProperties::VALIDATION => array(FieldProperties::FLOAT)
        ),
        /**
         * transaction currency ex. 985
         */
        'currency'            => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::FLOAT,
            FieldProperties::VALIDATION => array(FieldProperties::FLOAT, 'maxlength_3', 'minlength_3')
        ),
        /**
         * reason of rejection
         */
        'reason'              => array(
            FieldProperties::REQUIRED   => false,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING)
        )
    );
}
