<?php
/**
 *
 * @category    payment gateway
 * @package     Tpaycom_Magento2.1
 * @author      tpay.com
 * @copyright   (https://tpay.com)
 */

namespace tpaycom\magento2cards\Controller\tpaycards;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use tpaycom\magento2cards\Api\TpayCardsInterface;
use tpaycom\magento2cards\Block\Payment\tpaycards\Redirect as RedirectBlock;
use tpaycom\magento2cards\Model\CardTransaction;
use tpaycom\magento2cards\Service\TpayService;
use Magento\Checkout\Model\Session;

/**
 * Class Redirect
 *
 * @package tpaycom\magento2cards\Controller\tpaycardscards
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
     *
     * @param Context $context
     * @param TpayCardsInterface $tpayModel
     * @param TpayService $tpayService
     * @param Session $checkoutSession
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

        $paymentData = $this->tpayService->getPaymentData($orderId);
        $additionalPaymentInformation = $paymentData['additional_information'];
        if (!empty($additionalPaymentInformation['card_data'])
        ) {
            return $this->_redirect('magento2cards/tpaycards/CardPayment');
        } else {
            return $this->_redirect('checkout/cart');
        }
    }


}
