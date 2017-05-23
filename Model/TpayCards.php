<?php
/**
 *
 * @category    payment gateway
 * @package     Tpaycom_Magento2.1
 * @author      tpay.com
 * @copyright   (https://tpay.com)
 */

namespace tpaycom\magento2cards\Model;

use Magento\Checkout\Model\Session;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\Escaper;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Adapter;
use Magento\Payment\Model\Method\Logger;
use Magento\Quote\Api\Data\CartInterface;
use tpaycom\magento2cards\Api\Sales\CardsOrderRepositoryInterface;
use tpaycom\magento2cards\Api\TpayCardsInterface;
use tpaycom\magento2cards\lib\Validate;

/**
 * Class TpayCards
 *
 * @package tpaycom\magento2cards\Model
 */
class TpayCards extends AbstractMethod implements TpayCardsInterface
{
    /**#@+
     * Payment configuration
     */
    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
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

    /**
     * {@inheritdoc}
     *
     * @param UrlInterface $urlBuilder
     * @param Session $checkoutSession
     * @param CardsOrderRepositoryInterface $orderRepository
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
    public function getRSAKey()
    {
        return $this->getConfigData('rsa_key');
    }

    /**
     * {@inheritdoc}
     */
    public function getHashType()
    {
        return $this->getConfigData('hash_type');
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentRedirectUrl()
    {
        return $this->urlBuilder->getUrl('magento2cards/tpaycards/redirect', ['uid' => time() . uniqid(true)]);
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
    public function getTpayFormData($orderId = null)
    {
        $order = $this->getOrder($orderId);

        $billingAddress = $order->getBillingAddress();
        $amount = number_format($order->getGrandTotal(), 2);
        $name = $billingAddress->getData('firstname') . ' ' . $billingAddress->getData('lastname');

        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $resolver = $om->get('Magento\Framework\Locale\Resolver');
        $jezyk = $resolver->getLocale();


        return [
            'email'        => $this->escaper->escapeHtml($order->getCustomerEmail()),
            'nazwisko'     => $this->escaper->escapeHtml($name),
            'kwota'        => $amount,
            'opis'         => 'Zamówienie ' . $orderId,
            'crc'          => $orderId,
            'pow_url_blad' => $this->urlBuilder->getUrl('magento2cards/tpaycards/error'),
            'wyn_url'      => $this->urlBuilder->getUrl('magento2cards/tpaycards/notification'),
            'pow_url'      => $this->urlBuilder->getUrl('magento2cards/tpaycards/success'),
            'jezyk'        => $jezyk,
            'currency'     => $this->getISOCurrencyCode($order->getOrderCurrencyCode()),
        ];
    }

    /**
     *
     */
    protected function getOrder($orderId = null)
    {
        if ($orderId === null) {
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
        return Validate::validateCardCurrency($orderCurrency);
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
                || ($maxAmount && $quote->getBaseGrandTotal() > $maxAmount))
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
            static::CNAME,
            isset($additionalData[static::CNAME]) ? $additionalData[static::CNAME] : ''
        );
        $info->setAdditionalInformation(
            static::CEMAIL,
            isset($additionalData[static::CEMAIL]) ? $additionalData[static::CEMAIL] : ''
        );

        return $this;
    }
}
