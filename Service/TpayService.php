<?php
/**
 *
 * @category    payment gateway
 * @package     Tpaycom_Magento2.1
 * @author      tpay.com
 * @copyright   (https://tpay.com)
 */

namespace tpaycom\magento2cards\Service;

use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Sales\Model\Order;
use tpaycom\magento2cards\Api\Sales\CardsOrderRepositoryInterface;
use tpaycom\magento2cards\lib\ResponseFields;

/**
 * Class TpayService
 *
 * @package tpaycom\magento2cards\Service
 */
class TpayService
{
    /**
     * @var CardsOrderRepositoryInterface
     */
    protected $orderRepository;

    protected $builder;

    /**
     * TpayCards constructor.
     *
     * @param CardsOrderRepositoryInterface $orderRepository
     * @param BuilderInterface $builder
     */
    public function __construct(
        CardsOrderRepositoryInterface $orderRepository,
        BuilderInterface $builder
    ) {
        $this->orderRepository = $orderRepository;
        $this->builder = $builder;
    }

    /**
     * Change order state and notify user if needed
     *
     * @param int $orderId
     * @param bool $sendEmail
     *
     * @return Order
     */
    public function setOrderStatePendingPayment($orderId, $sendEmail)
    {
        /** @var Order $order */
        $order = $this->orderRepository->getByIncrementId($orderId);

        $order->addStatusToHistory(
            Order::STATE_PENDING_PAYMENT,
            __('Waiting for tpay.com payment.')
        );
        $order->setTotalDue($order->getGrandTotal())->setTotalPaid(0.00);
        $order->setSendEmail($sendEmail);
        $order->save();

        return $order;
    }

    /**
     * Return payment data
     *
     * @param int $orderId
     *
     * @return array
     */
    public function getPaymentData($orderId)
    {
        /** @var Order $order */
        $order = $this->orderRepository->getByIncrementId($orderId);

        return $order->getPayment()->getData();
    }

    /**
     * Validate order and set appropriate state
     *
     * @param int $orderId
     * @param array $validParams
     *
     * @return bool|Order
     */
    public function setOrderStatus($orderId, array $validParams)
    {
        /** @var Order $order */
        $order = $this->orderRepository->getByIncrementId($orderId);

        if (!$order->getId()) {
            return false;
        }
        $payment = $order->getPayment();
        $transactionDesc = $this->getTransactionDesc($validParams);
        if ($payment) {
            $payment->setLastTransId($validParams[ResponseFields::SALE_AUTH]);
            $payment->setTransactionId($validParams[ResponseFields::SALE_AUTH]);
            $payment->setAdditionalInformation(
                [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array)$validParams]
            );
            $trans = $this->builder;
            $transaction = $trans->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($validParams[ResponseFields::SALE_AUTH])
                ->setAdditionalInformation(
                    [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array)$validParams]
                )
                ->setFailSafe(true)
                //build method creates the transaction and returns the object
                ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);

            $payment->addTransactionCommentsToOrder(
                $transaction,
                $transactionDesc
            );
            $payment->setParentTransactionId(null);
            $payment->save();
            $transaction->save();
        }
        $orderAmount = (double)number_format($order->getGrandTotal(), 2);
        $trStatus = $validParams[ResponseFields::STATUS];
        $emailNotify = false;

        if ($trStatus === 'correct' && ((double)number_format($validParams[ResponseFields::AMOUNT],
                    2) === $orderAmount)
        ) {
            if ($order->getState() != Order::STATE_PROCESSING) {
                $emailNotify = true;
            }
            $status = __('The payment from tpay.com has been accepted.') . '</br>' . $transactionDesc;
            $state = Order::STATE_PROCESSING;
            $order->setTotalDue(0.00)->setTotalPaid($orderAmount);
        } else {
            if ($order->getState() != Order::STATE_HOLDED) {
                $emailNotify = true;
            }
            $status = __('Payment has been declined: ') . '</br>' . $transactionDesc;
            $state = Order::STATE_HOLDED;
        }
        $order->setState($state);
        $order->addStatusToHistory($state, $status, true);

        if ($emailNotify) {
            $order->setSendEmail(true);
        }
        $order->save();
        return $order;
    }

    /**
     * Get description for transaction
     *
     * @param array $validParams
     *
     * @return bool|string
     */
    protected function getTransactionDesc($validParams)
    {
        if (empty($validParams)) {
            return false;
        }
        $error = null;
        if ($validParams['status'] === 'declined') {
            $error = $validParams['reason'];
        }

        $transactionDesc = (is_null($error)) ? ' ' : ' Reason:  <b>' . $error . '</b>';
        $transactionDesc .= (isset($validParams[ResponseFields::TEST_MODE])) &&
        (int)$validParams[ResponseFields::TEST_MODE] === 1 ? '<b> TEST </b>' : ' ';
        return $transactionDesc;
    }
}
