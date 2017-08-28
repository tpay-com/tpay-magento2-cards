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
use Magento\Framework\App\Response\Http;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use tpaycom\magento2cards\Api\TpayCardsInterface;
use tpaycom\magento2cards\lib\PaymentCardFactory;
use tpaycom\magento2cards\Model\ApiProvider;
use tpaycom\magento2cards\Service\TpayService;

/**
 * Class Notification
 *
 * @package tpaycom\magento2cards\Controller\tpaycardscards
 */
class Notification extends Action
{
    /**
     * @var TpayCardsInterface
     */
    protected $tpay;

    /**
     * @var RemoteAddress
     */
    protected $remoteAddress;

    /**
     * @var bool
     */
    protected $emailNotify = false;

    /**
     * @var PaymentCardFactory
     */
    protected $paymentCardFactory;

    /**
     * @var TpayService
     */
    protected $tpayService;

    /**
     * {@inheritdoc}
     *
     * @param RemoteAddress $remoteAddress
     * @param TpayCardsInterface $tpayModel
     */
    public function __construct(
        Context $context,
        RemoteAddress $remoteAddress,
        TpayCardsInterface $tpayModel,
        PaymentCardFactory $paymentCardFactory,
        TpayService $tpayService
    ) {
        $this->tpay = $tpayModel;
        $this->remoteAddress = $remoteAddress;
        $this->paymentCardFactory = $paymentCardFactory;
        $this->tpayService = $tpayService;

        parent::__construct($context);
    }

    /**
     * @return bool
     */
    public function execute()
    {
        try {
            $paymentCards = (new ApiProvider($this->tpay, $this->paymentCardFactory))->getTpayPaymentCardFactory();
            $validParams = $paymentCards->handleNotification();
            $orderId = $validParams['order_id'];
            $localData = $this->tpay->getTpayFormData($orderId);
            $paymentCards->validateSign($validParams['sign'], isset($validParams['test_mode']) ? '1' : '',
                $validParams['sale_auth'], $validParams['order_id'], $validParams['card'], (double)$localData['kwota'],
                $validParams['date'], $localData['currency']);
            $this->tpayService->setOrderStatus($orderId, $validParams);

            return $this
                ->getResponse()
                ->setStatusCode(Http::STATUS_CODE_200)
                ->setContent('');

        } catch (\Exception $e) {
            return false;
        }
    }
}
