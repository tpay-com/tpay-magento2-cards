<?php
/**
 * Created by tpay.com.
 * Date: 26.04.2017
 * Time: 17:43
 */

namespace tpaycom\magento2cards\lib;


class DeregisterResponseFieldsSettings
{
    static $fields = array(
        /**
         * Method type
         */
        DeregisterResponseFields::TYPE      => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::OPTIONS),
            FieldProperties::OPTIONS    => array('deregister'),
        ),
        /**
         * Message checksum
         */
        DeregisterResponseFields::SIGN      => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING)
        ),
        /**
         * Created sale/refund id
         */
        DeregisterResponseFields::CLI_AUTH  => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING, 'maxlength_40')
        ),
        /**
         * Date of accounting/deregistering
         */
        DeregisterResponseFields::DATE      => array(
            FieldProperties::REQUIRED   => true,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING)
        ),
        /**
         * carry value of 1 if account has test mode, otherwise parameter not sent
         */
        DeregisterResponseFields::TEST_MODE => array(
            FieldProperties::REQUIRED   => false,
            FieldProperties::TYPE       => FieldProperties::STRING,
            FieldProperties::VALIDATION => array(FieldProperties::STRING, 'maxlength_1', 'minlength_1')
        ),
    );
}
