Magento2-TpayCards
======================

Tpay credit cards payment gateway Magento2 extension

Manual install
=======

1. Go to Magento2 root folder

2. Copy plugin files to app/code/tpaycom/magento2cards

3. If you have already installed the [magento2basic](https://github.com/tpay-com/tpay-magento2-basic) module, you can skip this step.  
Download and copy depending library [tpay-php](https://github.com/tpay-com/tpay-php) to app/code folder. In the result your should have 2 folders in app/code - tpaycom and tpayLibs.  

4. Enter following commands to enable module:

    ```bash
    php bin/magento module:enable tpaycom_magento2cards  
    php bin/magento setup:upgrade
    ```
5. Enable and configure module in Magento Admin under Stores/Configuration/Payment Methods/tpay.com credit cards

Composer install
=======

1. Enter following commands to download module:
    ```bash
    composer require tpaycom/magento2cards  
    ```
2. Enter following commands to enable module:

    ```bash
    php bin/magento module:enable tpaycom_magento2cards  
    php bin/magento setup:upgrade
    ```
3. Enable and configure module in Magento Admin under Stores/Configuration/Payment Methods/tpay.com credit cards
