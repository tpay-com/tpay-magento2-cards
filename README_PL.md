# Magento2-TpayCards

Bramka płatności [Tpay](https://tpay.com) kartami kredytowymi moduł Magento2. 

[![Najnowsza stabilna wersja](https://img.shields.io/packagist/v/tpaycom/magento2cards.svg?label=obecna%20wersja)](https://packagist.org/packages/tpaycom/magento2cards)
[![Wersja PHP](https://img.shields.io/packagist/php-v/tpaycom/magento2cards.svg)](https://php.net)
[![Licencja](https://img.shields.io/github/license/tpay-com/tpay-magento2-cards.svg?label=licencja)](LICENSE)
[![CI status](https://github.com/tpay-com/tpay-magento2-cards/actions/workflows/ci.yaml/badge.svg?branch=master)](https://github.com/tpay-com/tpay-magento2-cards/actions)

[English version :gb: wersja angielska](./README.md)

## Instalacja ręczna

1. Przejdź do katalogu głównego Magento2.

2. Skopiuj pliki wtyczki do `app/code/tpaycom/magento2cards`.

3. Jeśli masz już zainstalowany moduł [`magento2basic`](https://github.com/tpay-com/tpay-magento2-basic), możesz pominąć ten krok.
   Pobierz i skopiuj wymaganą bibliotekę [`tpay-php`](https://github.com/tpay-com/tpay-php) do folderu `app/code`. W rezultacie powinieneś/powinnaś mieć 2 foldery w `app/code` - `tpaycom` oraz `tpayLibs`.

4. Wykonaj następujące polecenia, aby włączyć moduł:
    ```bash
    php bin/magento module:enable tpaycom_magento2cards
    php bin/magento setup:upgrade
    ```

5. Włącz i skonfiguruj moduł w panelu administratora Magento w `Stores/Configuration/Payment Methods/tpay.com credit cards`.


## Instalacja z użyciem [Composer](https://getcomposer.org)a

1. Wykonaj następujące polecenie, aby pobrać moduł:
    ```bash
    composer require tpaycom/magento2cards
    ```

2. Wykonaj następujące polecenia, aby włączyć moduł:
    ```bash
    php bin/magento module:enable tpaycom_magento2cards
    php bin/magento setup:upgrade
    ```

3. Włącz i skonfiguruj moduł w panelu administratora Magento w `Stores/Configuration/Payment Methods/tpay.com credit cards`.
