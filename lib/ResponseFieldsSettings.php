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
        FieldProperties::TYPE     => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::OPTIONS),
            FieldProperties::OPTIONS    => array('sale', 'refund'),
        ),
        /**
         * Merchant optional value
         */
        ResponseFields::ORDER_ID  => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING, 'maxlength__40')
        ),
        /**
         * Payment status
         */
        ResponseFields::STATUS    => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::OPTIONS),
            FieldProperties::OPTIONS    => array('correct', 'declined'),
        ),
        /**
         * Message checksum
         */
        ResponseFields::SIGN      => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING, 'maxlength_128', 'maxlength_40')
        ),
        /**
         * Created sale/refund id
         */
        ResponseFields::SALE_AUTH => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING, 'maxlength_40')
        ),
        /**
         * Created sale/refund id
         */
        ResponseFields::CLI_AUTH  => array(
            FieldProperties::REQUIRED   => false,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING, 'maxlength_40')
        ),
        /**
         * Date of accounting/deregistering
         */
        ResponseFields::DATE      => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING)
        ),
        /**
         * carry value of 1 if account has test mode, otherwise parameter not sent
         */
        ResponseFields::TEST_MODE => array(
            FieldProperties::REQUIRED   => false,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING, 'maxlength_1', 'minlength_1')
        ),
        /**
         * shortcut for client card number, eg ****5678
         */
        ResponseFields::CARD      => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING, 'maxlength_8', 'minlength_8')
        ),
        /**
         * transaction amount
         */
        ResponseFields::AMOUNT    => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::FLOAT,
            FieldProperties::VALIDATION => array(FieldProperties::FLOAT)
        ),
        /**
         * transaction currency ex. 985
         */
        ResponseFields::CURRENCY  => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::FLOAT,
            FieldProperties::VALIDATION => array(FieldProperties::FLOAT, 'maxlength_3', 'minlength_3')
        ),
        /**
         * reason of rejection
         */
        ResponseFields::REASON    => array(
            FieldProperties::REQUIRED   => false,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING)
        )
    );
}
