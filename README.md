# Magento2-TpayCards

[Tpay](https://tpay.com) credit cards payment gateway Magento2 module.

[![Latest stable version](https://img.shields.io/packagist/v/tpaycom/magento2cards.svg?label=current%20version)](https://packagist.org/packages/tpaycom/magento2cards)
[![PHP version](https://img.shields.io/packagist/php-v/tpaycom/magento2cards.svg)](https://php.net)
[![License](https://img.shields.io/github/license/tpay-com/tpay-magento2-cards.svg)](LICENSE)
[![CI status](https://github.com/tpay-com/tpay-magento2-cards/actions/workflows/ci.yaml/badge.svg?branch=master)](https://github.com/tpay-com/tpay-magento2-cards/actions)
[![Type coverage](https://shepherd.dev/github/tpay-com/tpay-magento2-cards/coverage.svg)](https://shepherd.dev/github/tpay-com/tpay-magento2-cards)

[Polish version :poland: wersja polska](./README_PL.md)

## Manual installation

1. Go to Magento2 root directory.

2. Copy plugin files to `app/code/tpaycom/magento2cards`.

3. If you have already installed the [`magento2basic`](https://github.com/tpay-com/tpay-magento2-basic) module, you can skip this step.
   Download and copy required library [`tpay-php`](https://github.com/tpay-com/tpay-php) to `app/code` folder. In the result you should have 2 folders in `app/code` - `tpaycom` and `tpayLibs`.

4. Execute following commands to enable module:
    ```bash
    php bin/magento module:enable tpaycom_magento2cards
    php bin/magento setup:upgrade
    ```

5. Enable and configure module in Magento Admin under `Stores/Configuration/Payment Methods/tpay.com credit cards`.


## [Composer](https://getcomposer.org) installation

1. Execute following command to download module:
    ```bash
    composer require tpaycom/magento2cards
    ```

2. Execute following commands to enable module:
    ```bash
    php bin/magento module:enable tpaycom_magento2cards
    php bin/magento setup:upgrade
    ```

3. Enable and configure module in Magento Admin under `Stores/Configuration/Payment Methods/tpay.com credit cards`.
