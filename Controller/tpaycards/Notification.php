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
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use tpaycom\magento2cards\Api\TpayCardsInterface;
use tpaycom\magento2cards\lib\PaymentCard;
use tpaycom\magento2cards\lib\PaymentCardFactory;
use tpaycom\magento2cards\Model\ApiProvider;
use tpaycom\magento2cards\Service\TpayService;
use tpaycom\magento2cards\Service\TpayTokensService;
use Magento\Framework\Model\Context as ModelContext;
use Magento\Framework\Registry;

/**
 * Class Notification
 *
 * @package tpaycom\magento2cards\Controller\tpaycardscards
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
     * @var PaymentCardFactory
     */
    protected $paymentCardFactory;

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
     * @param PaymentCard $paymentCards
     */
    private $paymentCards;

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
        TpayService $tpayService,
        ModelContext $modelContext,
        Registry $registry
    ) {
        $this->tpay = $tpayModel;
        $this->remoteAddress = $remoteAddress;
        $this->paymentCardFactory = $paymentCardFactory;
        $this->tpayService = $tpayService;
        $this->modelContext = $modelContext;
        $this->registry = $registry;

        parent::__construct($context);
    }

    /**
     * @return bool
     */
    public function execute()
    {
        try {
            $this->paymentCards = (new ApiProvider($this->tpay, $this->paymentCardFactory))->getTpayPaymentCardFactory();
            $params = $this->getRequest()->getParams();
            $validParams = $this->paymentCards->handleNotification($params);
            isset($validParams['type']) && $validParams['type'] === 'deregister' ?
                $this->deregisterCard($validParams) : $this->checkPaymentNotification($validParams);
            return $this
                ->getResponse()
                ->setStatusCode(Http::STATUS_CODE_200)
                ->setContent(json_encode(array('result' => '1')));
        } catch (\Exception $e) {
            return false;
        }
    }

    private function deregisterCard($validParams)
    {
        $this->paymentCards->validateDeregisterSign($validParams['sign'], $validParams['cli_auth'],
            $validParams['date'], isset($validParams['test_mode'])? $validParams['test_mode'] : '');
        (new TpayTokensService($this->modelContext, $this->registry))
            ->deleteCustomerToken($validParams['cli_auth']);
    }

    private function checkPaymentNotification($validParams)
    {
        $orderId = $validParams['order_id'];
        $localData = $this->tpay->getTpayFormData($orderId);
        $this->paymentCards->validateSign($validParams['sign'], isset($validParams['test_mode']) ? '1' : '',
            $validParams['sale_auth'], $validParams['order_id'], $validParams['card'], (double)$localData['kwota'],
            $validParams['date'], $localData['currency'],
            isset($validParams['cli_auth']) ? $validParams['cli_auth'] : '');
        $this->tpayService->setOrderStatus($orderId, $validParams, $this->tpay);
        $payment = $this->tpayService->getPayment($orderId);
        $paymentData = $payment->getData();
        $additionalPaymentInformation = $paymentData['additional_information'];

        if (isset($validParams['cli_auth'])) {
            (new TpayTokensService($this->modelContext, $this->registry))
                ->setCustomerToken($this->tpay->getCustomerId($orderId), $validParams['cli_auth'],
                    $validParams['card'], $additionalPaymentInformation['card_vendor']);
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
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException
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
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
