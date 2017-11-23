magento2-tpaycards
======================

tpaycards payment gateway Magento2 extension

Manual install
=======

1. Go to Magento2 root folder

2. Copy plugin files to app/code/tpaycom/magento2cards

3. Enter following commands to enable module:

    ```bash
    php bin/magento module:enable tpaycom_magento2cards  
    php bin/magento setup:upgrade
    ```
4. Enable and configure module in Magento Admin under Stores/Configuration/Payment Methods/tpay.com credit cards

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
