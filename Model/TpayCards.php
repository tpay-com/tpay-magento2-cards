<?php

namespace tpaycom\magento2cards\Model;

use Magento\Checkout\Model\Session;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
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

/**
 * Class TpayCards
 */
class TpayCards extends AbstractMethod implements TpayCardsInterface
{
    use FieldsValidator;

    /**
     * Payment configuration
     */
    protected $_code = self::CODE;

    protected $_isGateway = true;
    protected $_canCapture = false;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    /*#@-*/

    protected $availableCurrencyCodes = ['PLN'];
    protected $termsURL = 'https://secure.tpay.com/regulamin.pdf';

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var CardsOrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var Escaper
     */
    protected $escaper;

    private $supportedVendors = [
        'visa',
        'jcb',
        'dinersclub',
        'maestro',
        'amex',
        'mastercard',
    ];

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    public function getRSAKey()
    {
        return $this->getConfigData('rsa_key');
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentRedirectUrl()
    {
        return $this->urlBuilder->getUrl('magento2cards/tpaycards/redirect', ['uid' => time().uniqid(true)]);
    }

    /**
     * {@inheritdoc}
     */
    public function getTermsURL()
    {
        return $this->termsURL;
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSendMail()
    {
        return $this->getConfigData('send_invoice_email');
    }

    /**
     * {@inheritdoc}
     */
    public function getTpayFormData($orderId = null)
    {
        $order = $this->getOrder($orderId);
        $billingAddress = $order->getBillingAddress();
        $amount = number_format($order->getGrandTotal(), 2, '.', '');
        $name = $billingAddress->getData('firstname').' '.$billingAddress->getData('lastname');
        $companyName = $billingAddress->getData('company');
        if (strlen($name) <= 3 && !empty($companyName) && strlen($companyName) > 3) {
            $name = $companyName;
        }
        $om = ObjectManager::getInstance();
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

    /**
     * @param int $orderId
     *
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    protected function getOrder($orderId = null)
    {
        if (null === $orderId) {
            $orderId = $this->getCheckout()->getLastRealOrderId();
        }

        return $this->orderRepository->getByIncrementId($orderId);
    }

    /**
     * @return Session
     */
    protected function getCheckout()
    {
        return $this->checkoutSession;
    }

    public function getISOCurrencyCode($orderCurrency)
    {
        return $this->validateCardCurrency($orderCurrency);
    }

    /**
     * {@inheritdoc}
     *
     * Check that tpay.com payments should be available.
     */
    public function isAvailable(CartInterface $quote = null)
    {
        $minAmount = $this->getConfigData('min_order_total');
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

    /**
     * {@inheritdoc}
     */
    public function getMidType()
    {
        return $this->getConfigData('mid_type');
    }

    /**
     * {@inheritdoc}
     */
    public function assignData(DataObject $data)
    {
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
     * @param InfoInterface|Payment $payment
     * @param float                 $amount
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

    /**
     * {@inheritdoc}
     */
    public function getApiKey()
    {
        return $this->getConfigData('api_key');
    }

    /**
     * {@inheritdoc}
     */
    public function getApiPassword()
    {
        return $this->getConfigData('api_pass');
    }

    /**
     * {@inheritdoc}
     */
    public function getVerificationCode()
    {
        return $this->getConfigData('verification_code');
    }

    /**
     * {@inheritdoc}
     */
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
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');
        return $customerSession->getCustomerId();
    }

    /**
     * {@inheritdoc}
     */
    public function getCardSaveEnabled()
    {
        return (bool)$this->getConfigData('card_save_enabled');
    }

    private function getMagentoVersion()
    {
        $objectManager = ObjectManager::getInstance();
        $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
        return $productMetadata->getVersion();
    }
}
