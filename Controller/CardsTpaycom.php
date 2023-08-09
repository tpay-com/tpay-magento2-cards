<?php

namespace tpaycom\magento2cards\Controller;

abstract class CardsTpaycom extends \Magento\Framework\App\Action\Action
{
    // Local IP address
    const LOCAL_IP = '127.0.0.1';

    // STR EMPTY
    const STR_EMPTY = '';

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \tpaycom\magento2cards\Model\TpayCards
     */
    protected $_model;

    /**
     * Locale Resolver
     *
     * @var \Magento\Framework\Locale\Resolver
     */
    protected $localeResolver;

    private $objectManager;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \tpaycom\magento2cards\Model\TpayCards $model,
        \Magento\Framework\Locale\Resolver $localeResolver
    ) {
        $this->_customerSession = $customerSession;
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        $this->_model = $model;
        $this->localeResolver = $localeResolver;
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        parent::__construct($context);

        $this->_model->setCustomerID($this->_customerSession->getCustomerId());
    }

    protected function getDotAmount()
    {
        return $this->getFormatAmount($this->_checkoutSession->getLastRealOrder()->getGrandTotal());
    }
}
