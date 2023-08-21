<?php

namespace tpaycom\magento2cards\Model;

use Magento\Checkout\Model\Session;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\Validator\Exception;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order\Payment;
use tpaycom\magento2cards\Api\Sales\CardsOrderRepositoryInterface;
use tpaycom\magento2cards\Api\TpayCardsInterface;
use tpaycom\magento2cards\Controller\tpaycards\CardRefunds;
use tpayLibs\src\_class_tpay\Validators\FieldsValidator;

class TpayCards extends AbstractMethod implements TpayCardsInterface
{
    use FieldsValidator;

    /** Payment configuration */
    protected $_code = self::CODE;

    protected $_isGateway = true;
    protected $_canCapture = false;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $availableCurrencyCodes = ['PLN'];
    protected $termsURL = 'https://secure.tpay.com/regulamin.pdf';

    /** @var UrlInterface */
    protected $urlBuilder;

    /** @var Session */
    protected $checkoutSession;

    /** @var CardsOrderRepositoryInterface */
    protected $orderRepository;

    /** @var Escaper */
    protected $escaper;

    private $supportedVendors = [
        'visa',
        'jcb',
        'dinersclub',
        'maestro',
        'amex',
        'mastercard',
    ];

    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        UrlInterface $urlBuilder,
        Session $checkoutSession,
        CardsOrderRepositoryInterface $orderRepository,
        Escaper $escaper,
        $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->escaper = $escaper;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            null,
            null,
            $data
        );
    }

    public function getRSAKey()
    {
        return $this->getConfigData('rsa_key');
    }

    public function getPaymentRedirectUrl()
    {
        return $this->urlBuilder->getUrl('magento2cards/tpaycards/redirect', ['uid' => time().uniqid(true)]);
    }

    public function getTermsURL()
    {
        return $this->termsURL;
    }

    public function getInvoiceSendMail()
    {
        return $this->getConfigData('send_invoice_email');
    }

    public function getTpayFormData($orderId = null)
    {
        $order = $this->getOrder($orderId);
        $billingAddress = $order->getBillingAddress();
        $amount = number_format($order->getGrandTotal(), 2, '.', '');
        $name = $billingAddress->getData('firstname').' '.$billingAddress->getData('lastname');

        /** @var string $companyName */
        $companyName = $billingAddress->getData('company');

        if (strlen($name) <= 3 && !empty($companyName) && strlen($companyName) > 3) {
            $name = $companyName;
        }
        $om = ObjectManager::getInstance();

        /** @var Resolver $resolver */
        $resolver = $om->get('Magento\Framework\Locale\Resolver');

        $language = $this->validateCardLanguage($resolver->getLocale());

        return [
            'email' => $this->escaper->escapeHtml($order->getCustomerEmail()),
            'name' => $this->escaper->escapeHtml($name),
            'amount' => $amount,
            'description' => 'Zamówienie '.$orderId,
            'crc' => $orderId,
            'error_url' => $this->urlBuilder->getUrl('magento2cards/tpaycards/error'),
            'success_url' => $this->urlBuilder->getUrl('magento2cards/tpaycards/success'),
            'language' => $language,
            'currency' => $this->getISOCurrencyCode($order->getOrderCurrencyCode()),
            'module' => 'Magento '.$this->getMagentoVersion(),
        ];
    }

    public function getISOCurrencyCode($orderCurrency)
    {
        return $this->validateCardCurrency($orderCurrency);
    }

    /**
     * {@inheritDoc}
     *
     * Check that tpay.com payments should be available.
     */
    public function isAvailable(CartInterface $quote = null)
    {
        /** @var float|int $minAmount */
        $minAmount = $this->getConfigData('min_order_total');

        /** @var float|int $maxAmount */
        $maxAmount = $this->getConfigData('max_order_total');

        if ($quote
            && (
                $quote->getBaseGrandTotal() < $minAmount
                || ($maxAmount && $quote->getBaseGrandTotal() > $maxAmount)
            )
        ) {
            return false;
        }

        if ($quote && !$this->isAvailableForCurrency($quote->getCurrency()->getQuoteCurrencyCode())) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    public function getMidType()
    {
        return $this->getConfigData('mid_type');
    }

    public function assignData(DataObject $data)
    {
        /** @var array<string> $additionalData */
        $additionalData = $data->getData('additional_data');

        $info = $this->getInfoInstance();

        $info->setAdditionalInformation(
            static::CARDDATA,
            isset($additionalData[static::CARDDATA]) ? $additionalData[static::CARDDATA] : ''
        );
        $info->setAdditionalInformation(
            static::CARD_VENDOR,
            isset($additionalData[static::CARD_VENDOR])
            && in_array($additionalData[static::CARD_VENDOR], $this->supportedVendors)
                ? $additionalData[static::CARD_VENDOR] : 'undefined'
        );
        $info->setAdditionalInformation(
            static::CARD_SAVE,
            isset($additionalData[static::CARD_SAVE]) ? '1' === $additionalData[static::CARD_SAVE] : false
        );
        $info->setAdditionalInformation(
            static::CARD_ID,
            isset($additionalData[static::CARD_ID]) && is_numeric($additionalData[static::CARD_ID])
                ? $additionalData[static::CARD_ID] : false
        );

        return $this;
    }

    /**
     * Payment refund
     *
     * @param Payment $payment
     * @param float   $amount
     *
     * @throws Exception
     *
     * @return $this
     */
    public function refund(InfoInterface $payment, $amount)
    {
        $refunds = new CardRefunds(
            $this->getApiKey(),
            $this->getApiPassword(),
            $this->getVerificationCode(),
            $this->getRSAKey(),
            $this->getHashType()
        );
        $order = $payment->getOrder();
        $transactionId = $refunds->makeRefund($payment, $amount, $order->getOrderCurrencyCode());
        try {
            if ($transactionId) {
                $payment
                    ->setTransactionId($transactionId)
                    ->setParentTransactionId($payment->getParentTransactionId())
                    ->setIsTransactionClosed(1)
                    ->setShouldCloseParentTransaction(1);
            }
        } catch (\Exception $e) {
            $this->logger->debug(['transaction_id' => $transactionId, 'exception' => $e->getMessage()]);
            $this->_logger->error(__('Payment refunding error.'));
            throw new Exception(__('Payment refunding error.'));
        }

        return $this;
    }

    public function getApiKey()
    {
        return $this->getConfigData('api_key');
    }

    public function getApiPassword()
    {
        return $this->getConfigData('api_pass');
    }

    public function getVerificationCode()
    {
        return $this->getConfigData('verification_code');
    }

    public function getHashType()
    {
        return $this->getConfigData('hash_type');
    }

    /**
     * @param int $orderId
     *
     * @return string
     */
    public function getCustomerId($orderId)
    {
        $order = $this->getOrder($orderId);

        return $order->getCustomerId();
    }

    /**
     * check if customer was logged while placing order
     *
     * @param int $orderId
     *
     * @return bool
     */
    public function isCustomerGuest($orderId)
    {
        $order = $this->getOrder($orderId);

        return $order->getCustomerIsGuest();
    }

    /**
     * check if customer is logged in on current session
     *
     * @return bool
     */
    public function isCustomerLoggedIn()
    {
        $objectManager = ObjectManager::getInstance();

        /** @var \Magento\Customer\Model\Session $customerSession */
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');

        return $customerSession->isLoggedIn();
    }

    /**
     * check for customer ID on current session
     *
     * @return bool
     */
    public function getCheckoutCustomerId()
    {
        $objectManager = ObjectManager::getInstance();

        /** @var \Magento\Customer\Model\Session $customerSession */
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');

        return $customerSession->getCustomerId();
    }

    public function getCardSaveEnabled()
    {
        return (bool) $this->getConfigData('card_save_enabled');
    }

    /**
     * @param int $orderId
     *
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    protected function getOrder($orderId = null)
    {
        if (null === $orderId) {
            /** @var int $orderId */
            $orderId = $this->getCheckout()->getLastRealOrderId();
        }

        return $this->orderRepository->getByIncrementId($orderId);
    }

    /** @return Session */
    protected function getCheckout()
    {
        return $this->checkoutSession;
    }

    /**
     * Availability for currency
     *
     * @param string $currencyCode
     *
     * @return bool
     */
    protected function isAvailableForCurrency($currencyCode)
    {
        return (!in_array($currencyCode, $this->availableCurrencyCodes) && !$this->getMidType()) ? false : true;
    }

    private function getMagentoVersion()
    {
        $objectManager = ObjectManager::getInstance();

        /** @var ProductMetadataInterface $productMetadata */
        $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');

        return $productMetadata->getVersion();
    }
}
