<?php

namespace tpaycom\magento2cards\Controller\tpaycards;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use tpaycom\magento2cards\Api\TpayCardsInterface;
use tpaycom\magento2cards\Service\TpayService;

/**
 * Class Redirect
 */
class Redirect extends Action
{
    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var TpayService
     */
    protected $tpayService;

    /**
     * @var TpayCardsInterface
     */
    private $tpay;

    /**
     * Redirect constructor.
     */
    public function __construct(
        Context $context,
        TpayCardsInterface $tpayModel,
        TpayService $tpayService,
        Session $checkoutSession
    ) {
        $this->tpayService = $tpayService;
        $this->checkoutSession = $checkoutSession;
        $this->tpay = $tpayModel;

        parent::__construct($context);
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $uid = $this->getRequest()->getParam('uid');
        $orderId = $this->checkoutSession->getLastRealOrderId();

        if (!$orderId || !$uid) {
            return $this->_redirect('checkout/cart');
        }
        return $this->_redirect('magento2cards/tpaycards/CardPayment');
    }
}
