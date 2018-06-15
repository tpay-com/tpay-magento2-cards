<?php

/**
 * @category    payment gateway
 * @package     tpaycom_magento2cards
 * @author      tpay.com
 * @copyright   (https://tpay.com)
 */

namespace tpaycom\magento2cards\lib;

/**
 * Class Validate
 *
 * Include methods responsible for receiving and validating input data
 *
 * @package tpaycom\magento2cards\lib
 */
class Validate
{
    private static $responseFields = [];

    private static $cardPaymentLanguages = array(
        'pl' => 'pl_PL',
        'en' => 'en_EN',
        'es' => 'sp_SP',
        'it' => 'it_IT',
        'ru' => 'ru_RU',
        'fr' => 'fr_FR',
    );
    const PAYMENT_TYPE_CARD = 'card';
    const CARD_DEREGISTER = 'deregister';

    public static function validateCardCurrency($currency)
    {
        if (strlen($currency) !== 3) {
            throw new \Exception('Currency is invalid.');
        }
        $currencies = CurrencyISOCodes::ISO_CURRENCY_CODES;
        switch (gettype($currency)) {
            case 'string':
                if (in_array($currency, $currencies)) {
                    $currency = array_search($currency, $currencies);
                } elseif (array_key_exists((int)$currency, $currencies)) {
                    $currency = (int)$currency;
                } else {
                    throw new \Exception('Currency is not supported.');
                }

                break;
            case 'integer':
                if (!array_key_exists($currency, $currencies)) {
                    throw new \Exception('Currency is not supported.');
                }
                break;
            default:
                throw new \Exception('Currency variable type not supported.');
        }
        return $currency;

    }

    /**
     * Check length of field
     *
     * @param string $validator requeries for field
     * @param mixed  $value     field value
     * @param string $name      field name
     *
     * @throws \Exception
     */
    public static function fieldLengthValidation($validator, $value, $name)
    {
        if (strpos($validator, 'maxlenght') === 0) {
            $max = explode('_', $validator);
            $max = (int)$max[1];
            self::validateMaxLenght($value, $max, $name);
        }
        if (strpos($validator, 'minlength') === 0) {
            $min = explode('_', $validator);
            $min = (int)$min[1];
            self::validateMinLength($value, $min, $name);
        }
    }

    /**
     * Check all variables required in response
     * Parse variables to valid types
     *
     * @param string $paymentType
     *
     * @param $params
     * @return array
     * @throws \Exception
     */
    public static function getResponse($paymentType, $params)
    {
        $ready = array();
        $missed = array();

        switch ($paymentType) {
            case static::PAYMENT_TYPE_CARD:
                static::$responseFields = ResponseFieldsSettings::$fields;
                break;
            case static::CARD_DEREGISTER:
                static::$responseFields = DeregisterResponseFieldsSettings::$fields;
                break;
            default:
                throw new \Exception(sprintf('unknown payment type %s', $paymentType));
        }

        foreach (static::$responseFields as $fieldName => $field) {
            if (Util::post($fieldName, FieldProperties::STRING, $params) === false) {
                if ($field[FieldProperties::REQUIRED] === true) {
                    $missed[] = $fieldName;
                }
            } else {
                $val = Util::post($fieldName, FieldProperties::STRING, $params);
                switch ($field[FieldProperties::TYPE]) {
                    case FieldProperties::STRING:
                        $val = (string)$val;
                        break;
                    case 'int':
                        $val = (int)$val;
                        break;
                    case FieldProperties::FLOAT:
                        $val = (float)$val;
                        break;
                    default:
                        throw new \Exception(sprintf('unknown field type in getResponse - field name= %s', $fieldName));
                }
                $ready[$fieldName] = $val;
            }
        }

        if (count($missed) > 0) {
            throw new \Exception(sprintf('Missing fields in tpaycards response: %s', implode(',', $missed)));
        }

        foreach ($ready as $fieldName => $fieldVal) {
            static::validateOne($fieldName, $fieldVal);
        }

        return $ready;
    }

    /**
     * Check if variable is uint
     *
     * @param mixed  $value variable to check
     * @param string $name  field name
     *
     * @throws \Exception
     */
    private static function validateUint($value, $name)
    {
        if (!is_int($value)) {
            throw new \Exception(sprintf('Field "%s" must be an integer', $name));
        } else {
            if ($value < 0) {
                throw new \Exception(sprintf('Field "%s" must be higher than zero', $name));
            }
        }
    }
    /**
     * Check one field form
     *
     * @param string $name  field name
     * @param mixed  $value field value
     *
     * @return bool
     *
     * @throws \Exception
     */
    public static function validateOne($name, $value)
    {
        $requestFields = static::$responseFields;

        if (!is_string($name)) {
            throw new \Exception('Invalid field name');
        }
        if (!array_key_exists($name, $requestFields)) {
            throw new \Exception('Field with this name is not supported');
        }

        $fieldConfig = $requestFields[$name];

        if ($fieldConfig[FieldProperties::REQUIRED] === false && ($value === '' || $value === false)) {
            return true;
        }

        if (isset($fieldConfig[FieldProperties::VALIDATION]) === true) {
            static::fieldValidation($value, $name);
        }

        return true;
    }

    /**
     * Check that the field is correct
     *
     * @param mixed  $value field value
     * @param string $name  field name
     *
     * @throws \Exception
     */
    public static function fieldValidation($value, $name)
    {
        $fieldConfig = static::$responseFields[$name];
        foreach ($fieldConfig[FieldProperties::VALIDATION] as $validator) {
            switch ($validator) {
                case 'uint':
                    self::validateUint($value, $name);
                    break;
                case Type::FLOAT:
                    self::validateFloat($value, $name);
                    break;
                case Type::STRING:
                    self::validateString($value, $name);
                    break;
                case 'email_list':
                    self::validateEmailList($value, $name);
                    break;
                case FieldProperties::OPTIONS:
                    self::validateOptions($value, $fieldConfig[FieldProperties::OPTIONS], $name);
                    break;
                default:
            }
            static::fieldLengthValidation($validator, $value, $name);
        }
    }

    /**
     * Check if variable is float
     *
     * @param mixed  $value variable to check
     * @param string $name  field name
     *
     * @throws \Exception
     */
    private static function validateFloat($value, $name)
    {
        if (!is_float($value) && !is_int($value)) {
            throw new \Exception(sprintf('Field "%s" must be a float|int number', $name));
        } else {
            if ($value < 0) {
                throw new \Exception(sprintf('Field "%s" must be higher than zero', $name));
            }
        }
    }

    /**
     * Check if variable is string
     *
     * @param mixed  $value variable to check
     * @param string $name  field name
     *
     * @throws \Exception
     */
    private static function validateString($value, $name)
    {
        if (!is_string($value)) {
            throw new \Exception(sprintf('Field "%s" must be a string', $name));
        }
    }

    /**
     * Check if variable is valid email list
     *
     * @param mixed  $value variable to check
     * @param string $name  field name
     *
     * @throws \Exception
     */
    private static function validateEmailList($value, $name)
    {
        if (!is_string($value)) {
            throw new \Exception(sprintf('Field "%s" must be a string', $name));
        }
        $emails = explode(',', $value);
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new \Exception(
                    sprintf('Field "%s" contains invalid email address', $name)
                );
            }
        }
    }

    /**
     * Check if variable has expected value
     *
     * @param mixed  $value   variable to check
     * @param array  $options available options
     * @param string $name    field name
     *
     * @throws \Exception
     */
    private static function validateOptions($value, $options, $name)
    {
        if (!in_array($value, $options, true)) {
            throw new \Exception(sprintf('Field "%s" has unsupported value', $name));
        }
    }

    /**
     * Check variable max lenght
     *
     * @param mixed  $value variable to check
     * @param int    $max   max lenght
     * @param string $name  field name
     *
     * @throws \Exception
     */
    private static function validateMaxLenght($value, $max, $name)
    {
        if (strlen($value) > $max) {
            throw new \Exception(
                sprintf('Value of field "%s" is too long. Max %d characters', $name, $max)
            );
        }
    }

    /**
     * Check variable min length
     *
     * @param mixed  $value variable to check
     * @param int    $min   min length
     * @param string $name  field name
     *
     * @throws \Exception
     */
    private static function validateMinLength($value, $min, $name)
    {
        if (strlen($value) < $min) {
            throw new \Exception(
                sprintf('Value of field "%s" is too short. Min %d characters', $name, $min)
            );
        }
    }

    /**
     * Validate merchant Id
     *
     * @param int $merchantId
     *
     * @throws \Exception
     */
    public static function validateMerchantId($merchantId)
    {
        if (!is_int($merchantId) || $merchantId <= 0) {
            throw new \Exception('Invalid merchantId');
        }
    }

    /**
     * Validate merchant secret
     *
     * @param string $merchantSecret
     *
     * @throws \Exception
     */
    public static function validateMerchantSecret($merchantSecret)
    {
        if (!is_string($merchantSecret) || strlen($merchantSecret) === 0) {
            throw new \Exception('Invalid secret code');
        }
    }

    /**
     * Return field value
     *
     * @param $field array
     * @param $val mixed
     * @return mixed
     * @throws \Exception
     */
    private static function getFieldValue($field, $val)
    {
        switch ($field[FieldProperties::TYPE]) {
            case Type::STRING:
                $val = (string)$val;
                break;
            case Type::INT:
                $val = (int)$val;
                break;
            case Type::FLOAT:
                $val = (float)$val;
                break;
            default:
                throw new \Exception(sprintf('unknown field type in getResponse - field name= %s', $field));
        }

        return $val;
    }
    /**
 * Validate card payment language
 *
 * @param string $language
 *
 * @throws \Exception
 * @return string
 */
    public static function validateCardLanguage($language)
    {
        if (!is_string($language)) {
            throw new \Exception('Invalid language value type.');
        }
        if (in_array($language, static::$cardPaymentLanguages)) {
            $language = array_search($language, static::$cardPaymentLanguages);
        } elseif (!array_key_exists($language, static::$cardPaymentLanguages)) {
            $language = 'en';
        }
        return $language;

    }
    /**
     * Validate Card Api Key
     *
     * @param string $cardApiKey
     *
     * @throws \Exception
     */
    public static function validateCardApiKey($cardApiKey)
    {
        if (!is_string($cardApiKey) || strlen($cardApiKey) === 0) {
            throw new \Exception('Invalid card API key');
        }
    }

    /**
     * Validate Card Api Password
     *
     * @param string $cardApiPassword
     *
     * @throws \Exception
     */
    public static function validateCardApiPassword($cardApiPassword)
    {
        if (!is_string($cardApiPassword) || strlen($cardApiPassword) === 0) {
            throw new \Exception('Invalid card API pass');
        }
    }

    /**
     * Validate card verification code
     *
     * @param string $cardCode
     *
     * @throws \Exception
     */
    public static function validateCardCode($cardCode)
    {
        if (!is_string($cardCode) || strlen($cardCode) === 0 || strlen($cardCode) > 40) {
            throw new \Exception('Invalid card code');
        }
    }

    /**
     * Validate card hash algorithm
     * @param string $hashAlg
     * @throws \Exception
     */
    public static function validateCardHashAlg($hashAlg)
    {
        if (!in_array($hashAlg, array('sha1', 'sha256', 'sha512', 'ripemd160', 'ripemd320', 'md5'))) {
            throw new \Exception('Invalid hash algorithm');
        }
    }

    /**
     * Validate card RSA key
     *
     * @param string $keyRSA
     *
     * @throws \Exception
     */
    public static function validateCardRSAKey($keyRSA)
    {
        if (!is_string($keyRSA) || strlen($keyRSA) === 0) {
            throw new \Exception('Invalid card RSA key');
        }
    }

}

