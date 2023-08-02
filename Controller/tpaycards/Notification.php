<?php
/**
 *
 * @category    payment gateway
 * @package     Tpaycom_Magento2.3
 * @author      tpay.com
 * @copyright   (https://tpay.com)
 */

namespace tpaycom\magento2cards\Controller\tpaycards;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use tpaycom\magento2cards\Model\CardTransactionModel;
use tpaycom\magento2cards\Model\CardTransactionModelFactory;
use tpaycom\magento2cards\Api\TpayCardsInterface;
use tpaycom\magento2cards\Service\TpayService;
use tpaycom\magento2cards\Service\TpayTokensService;
use Magento\Framework\Model\Context as ModelContext;
use Magento\Framework\Registry;
use tpayLibs\src\_class_tpay\Utilities\Util;

/**
 * Class Notification
 *
 * @package tpaycom\magento2cards\Controller\tpaycards
 */
class Notification extends Action implements CsrfAwareActionInterface
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
     * @var CardTransactionModelFactory
     */
    protected $cardTransactionFactory;

    /**
     * @var TpayService
     */
    protected $tpayService;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var ModelContext
     */
    private $modelContext;

    /**
     * @var CardTransactionModel
     */
    private $cardTransactionModel;

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
        CardTransactionModelFactory $paymentCardFactory,
        TpayService $tpayService,
        ModelContext $modelContext,
        Registry $registry
    ) {
        $this->tpay = $tpayModel;
        $this->remoteAddress = $remoteAddress;
        $this->cardTransactionFactory = $paymentCardFactory;
        $this->tpayService = $tpayService;
        $this->modelContext = $modelContext;
        $this->registry = $registry;
        $this->cardTransactionModel = $this->cardTransactionFactory->create(
            [
                'apiPassword' => $this->tpay->getApiPassword(),
                'apiKey' => $this->tpay->getApiKey(),
                'verificationCode' => $this->tpay->getVerificationCode(),
                'keyRsa' => $this->tpay->getRSAKey(),
                'hashType' => $this->tpay->getHashType(),
            ]
        );
        Util::$loggingEnabled = false;

        parent::__construct($context);
    }

    /**
     * @return bool
     */
    public function execute()
    {
        try {
            $validParams = $this->cardTransactionModel->handleNotification();
            isset($validParams['type']) && $validParams['type'] === 'deregister' ?
                $this->deregisterCard($validParams) : $this->processSaleNotification($validParams);

            return $this
                ->getResponse()
                ->setStatusCode(Http::STATUS_CODE_200);
        } catch (\Exception $e) {
            return false;
        }

    }

    /**
     * Create exception in case CSRF validation failed.
     * Return null if default exception will suffice.
     *
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request)
    {
        return null;
    }

    /**
     * Perform custom request validation.
     * Return null if default validation is needed.
     *
     * @param RequestInterface $request
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request)
    {
        return true;
    }

    private function deregisterCard($validParams)
    {
        $this->cardTransactionModel
            ->setClientToken($validParams['cli_auth'])
            ->currency = '';
        $this->cardTransactionModel->validateCardSign(
            $validParams['sign'],
            '',
            '',
            $validParams['date'],
            '',
            isset($validParams['test_mode']) ? $validParams['test_mode'] : '',
            'deregister'
        );
        (new TpayTokensService($this->modelContext, $this->registry))
            ->deleteCustomerToken($validParams['cli_auth']);
    }

    private function processSaleNotification($validParams)
    {
        $orderId = $validParams['order_id'];
        $localOrderDetails = $this->tpay->getTpayFormData($orderId);
        $this->cardTransactionModel
            ->setCurrency($localOrderDetails['currency'])
            ->setAmount((double)$localOrderDetails['amount'])
            ->setOrderID($orderId);
        if (isset($validParams['cli_auth'])) {
            $this->cardTransactionModel->setClientToken($validParams['cli_auth']);
        }
        $this->cardTransactionModel->validateCardSign(
            $validParams['sign'],
            $validParams['sale_auth'],
            $validParams['card'],
            $validParams['date'],
            $validParams['status'],
            isset($validParams['test_mode']) ? '1' : ''
        );
        $this->tpayService->setOrderStatus($orderId, $validParams, $this->tpay);
        $payment = $this->tpayService->getPayment($orderId);
        $paymentData = $payment->getData();
        $additionalPaymentInformation = $paymentData['additional_information'];

        if (isset($validParams['cli_auth'])) {
            (new TpayTokensService($this->modelContext, $this->registry))
                ->setCustomerToken(
                    $this->tpay->getCustomerId($orderId),
                    $validParams['cli_auth'],
                    $validParams['card'],
                    $additionalPaymentInformation['card_vendor']
                );
        }
    }

}
