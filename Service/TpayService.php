<?php

namespace tpaycom\magento2cards\Service;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Operations\RegisterCaptureNotificationOperation;
use Magento\Sales\Model\Order\Payment\State\CommandInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Sales\Model\Order\Payment\Transaction\ManagerInterface;
use Magento\Sales\Model\Service\InvoiceService;
use tpaycom\magento2cards\Api\Sales\CardsOrderRepositoryInterface;
use tpaycom\magento2cards\Api\TpayCardsInterface;
use tpayLibs\src\Dictionaries\ISO_codes\CurrencyCodesDictionary;

class TpayService extends RegisterCaptureNotificationOperation
{
    /**
     * @var CardsOrderRepositoryInterface
     */
    protected $orderRepository;

    protected $builder;
    protected $invoiceService;
    private $objectManager;

    public function __construct(
        CardsOrderRepositoryInterface $orderRepository,
        BuilderInterface $builder,
        CommandInterface $stateCommand,
        BuilderInterface $transactionBuilder,
        ManagerInterface $transactionManager,
        EventManagerInterface $eventManager,
        InvoiceService $invoiceService
    ) {
        $this->orderRepository = $orderRepository;
        $this->builder = $builder;
        $this->objectManager = ObjectManager::getInstance();
        $this->invoiceService = $invoiceService;
        parent::__construct(
            $stateCommand,
            $transactionBuilder,
            $transactionManager,
            $eventManager
        );
    }

    /**
     * Change order state and notify user if needed
     *
     * @param int $orderId
     *
     * @return Order
     */
    public function setOrderStatePendingPayment($orderId)
    {
        /** @var Order $order */
        $order = $this->orderRepository->getByIncrementId($orderId);

        $order->setTotalDue($order->getGrandTotal())
            ->setTotalPaid(0.00)
            ->setBaseTotalPaid(0.00)
            ->setBaseTotalDue($order->getBaseGrandTotal())
            ->setState(Order::STATE_PENDING_PAYMENT)
            ->addStatusToHistory(true);

        $order->save();

        return $order;
    }

    public function addCommentToHistory($orderId, $comment)
    {
        /** @var Order $order */
        $order = $this->orderRepository->getByIncrementId($orderId);
        $order->addStatusToHistory($order->getState(), $comment);
        $order->save();
    }

    /**
     * Return payment data
     *
     * @param int $orderId
     *
     * @return OrderPaymentInterface
     */
    public function getPayment($orderId)
    {
        /** @var Order $order */
        $order = $this->orderRepository->getByIncrementId($orderId);

        return $order->getPayment();
    }

    /**
     * Validate order and set appropriate state
     *
     * @param int                $orderId
     * @param TpayCardsInterface $tpayModel
     *
     * @return bool|Order
     */
    public function setOrderStatus($orderId, array $validParams, $tpayModel)
    {
        /** @var Order $order */
        $order = $this->orderRepository->getByIncrementId($orderId);
        if (!$order->getId()) {
            return false;
        }
        $sendNewInvoiceMail = (bool)$tpayModel->getInvoiceSendMail();
        $transactionDesc = $this->getTransactionDesc($validParams);
        $orderAmount = (float)number_format($order->getGrandTotal(), 2, '.', '');
        $emailNotify = false;

        if (!isset($validParams['status']) || 'correct' !== $validParams['status']
            || ((float)number_format($validParams['amount'], 2, '.', '') !== $orderAmount)
        ) {
            $comment = __('Payment has been declined. ').'</br>'.$transactionDesc;
            $this->addCommentToHistory($orderId, $comment);
        } else {
            if (Order::STATE_PROCESSING != $order->getState()) {
                $emailNotify = true;
            }
            $this->registerCaptureNotificationTpay($order->getPayment(), $order->getGrandTotal(), $validParams);
        }

        if ($emailNotify) {
            $order->setSendEmail(true);
        }
        $order->save();
        if ($sendNewInvoiceMail) {
            /** @var Invoice $invoice */
            foreach ($order->getInvoiceCollection() as $invoice) {
                /** @var int $invoiceId */
                $invoiceId = $invoice->getId();

                $this->invoiceService->notify($invoiceId);
            }
        }

        return $order;
    }

    /**
     * Get description for transaction
     *
     * @param array<string> $validParams
     *
     * @return bool|string
     */
    protected function getTransactionDesc($validParams)
    {
        if (empty($validParams)) {
            return false;
        }
        if (isset($validParams['err_desc'])) {
            return 'Payment error: '.$validParams['err_desc'].', error code: '.$validParams['err_code'];
        }
        $error = null;
        if ('declined' === $validParams['status']) {
            $error = $validParams['reason'];
        }

        $transactionDesc = (is_null($error)) ? ' ' : ' Reason:  <b>'.$error.'</b>';
        $transactionDesc .= (isset($validParams['test_mode']))
        && 1 === (int)$validParams['test_mode'] ? '<b> TEST </b>' : ' ';

        return $transactionDesc;
    }

    /**
     * Registers capture notification.
     *
     * @param float|string $amount
     * @param array        $validParams
     * @param bool         $skipFraudDetection
     *
     * @throws \Exception
     */
    private function registerCaptureNotificationTpay(
        OrderPaymentInterface $payment,
        $amount,
        $validParams,
        $skipFraudDetection = false
    ) {
        /**
         * @var Payment $payment
         */
        $payment->setTransactionId(
            $this->transactionManager->generateTransactionId(
                $payment,
                Transaction::TYPE_CAPTURE,
                $payment->getAuthorizationTransaction()
            )
        );

        $order = $payment->getOrder();
        $amount = (float)$amount;
        $invoice = $this->getInvoiceForTransactionId($order, $payment->getTransactionId());
        $orderCurrency = $order->getOrderCurrency()->getCode();
        if (!in_array($orderCurrency, CurrencyCodesDictionary::CODES)) {
            throw new \Exception(sprintf('Order currency %s does not exist in Tpay dictionary!', $orderCurrency));
        }
        $orderCurrency = array_search($orderCurrency, CurrencyCodesDictionary::CODES);
        // register new capture
        if (!$invoice && $payment->isCaptureFinal($amount) && $orderCurrency === (int)$validParams['currency']) {
            $invoice = $order->prepareInvoice()->register();
            $invoice->setOrder($order);
            $order->addRelatedObject($invoice);
            $payment->setCreatedInvoice($invoice);
            $payment->setShouldCloseParentTransaction(true);
            $order->setState(Order::STATE_PROCESSING)->save();
        } else {
            $payment->setIsFraudDetected(!$skipFraudDetection);
            $this->updateTotals($payment, ['base_amount_paid_online' => $amount]);
        }

        if (!$payment->getIsTransactionPending() && $invoice && Invoice::STATE_OPEN === $invoice->getState()) {
            $invoice->setOrder($order);
            $invoice->pay();
            $this->updateTotals($payment, ['base_amount_paid_online' => $amount]);
            $order->addRelatedObject($invoice);
        }
        $payment->setTransactionId($validParams['sale_auth'])
            ->setTransactionAdditionalInfo(Transaction::RAW_DETAILS, $validParams);
        $transaction = $payment->addTransaction(
            Transaction::TYPE_CAPTURE,
            $invoice,
            true
        );
        $message = $this->stateCommand->execute($payment, $amount, $order);
        $message = $payment->prependMessage($message);
        $payment->addTransactionCommentsToOrder($transaction, $message);
    }
}
