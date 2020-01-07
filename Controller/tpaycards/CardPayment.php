<?php
/**
 *
 * @category    payment gateway
 * @package     Tpaycom_Magento2.3
 * @author      tpay.com
 * @copyright   (https://tpay.com)
 */

namespace tpaycom\magento2cards\Controller\tpaycards;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Model\Context as ModelContext;
use tpaycom\magento2cards\Api\TpayCardsInterface;
use tpaycom\magento2cards\Model\CardTransactionModel;
use tpaycom\magento2cards\Model\CardTransactionModelFactory;
use tpaycom\magento2cards\Service\TpayService;
use tpaycom\magento2cards\Service\TpayTokensService;
use Magento\Framework\Registry;
use tpayLibs\src\_class_tpay\Utilities\Util;

/**
 * Class CardPayment
 *
 * @package tpaycom\magento2cards\Controller\tpaycards
 */
class CardPayment extends Action
{
    const METHOD = 'method';
    const NAME = 'name';
    const EMAIL = 'email';
    const DESC = 'desc';
    const AMOUNT = 'amount';
    const CURRENCY = 'currency';
    const SIGN = 'sign';
    const APIPASS = 'api_password';
    const LANGUAGE = 'language';
    const SALE = 'sale';
    const SALEAUTH = 'sale_auth';
    const CLIAUTH = 'cli_auth';
    const ERROR_PATH = 'magento2cards/tpaycards/error';
    const SUCCESS_PATH = 'magento2cards/tpaycards/success';

    /**
     * @var TpayService
     */
    protected $tpayService;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var TpayCardsInterface
     */
    private $tpay;

    private $cardTransactionFactory;

    private $registry;

    private $modelContext;

    private $tokensService;

    /**
     * @var CardTransactionModel
     */
    private $cardTransactionModel;

    private $tpayPaymentConfig;

    /**
     * {@inheritdoc}
     *
     * @param TpayCardsInterface $tpayModel
     * @param TpayService $tpayService
     */
    public function __construct(
        Context $context,
        TpayCardsInterface $tpayModel,
        TpayService $tpayService,
        Session $checkoutSession,
        CardTransactionModelFactory $paymentCardFactory,
        ModelContext $modelContext,
        Registry $registry
    ) {
        $this->tpay = $tpayModel;
        $this->tpayService = $tpayService;
        $this->checkoutSession = $checkoutSession;
        $this->cardTransactionFactory = $paymentCardFactory;
        $this->modelContext = $modelContext;
        $this->registry = $registry;
        $this->tokensService = new TpayTokensService($this->modelContext, $this->registry);
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
     * {@inheritdoc}
     */
    public function execute()
    {
        $orderId = $this->checkoutSession->getLastRealOrderId();
        if ($orderId) {
            $payment = $this->tpayService->getPayment($orderId);
            $paymentData = $payment->getData();
            $this->tpayService->setOrderStatePendingPayment($orderId);
            $additionalPaymentInformation = $paymentData['additional_information'];
            $this->tpayPaymentConfig = $this->tpay->getTpayFormData($orderId);
            $this->cardTransactionModel
                ->setEnablePowUrl(true)
                ->setReturnUrls($this->tpayPaymentConfig['success_url'], $this->tpayPaymentConfig['error_url'])
                ->setAmount($this->tpayPaymentConfig['amount'])
                ->setCurrency($this->tpayPaymentConfig['currency'])
                ->setLanguage(strtolower($this->tpayPaymentConfig['language']))
                ->setOrderID($this->tpayPaymentConfig['crc'])
                ->setModuleName($this->tpayPaymentConfig['module']);

            if (isset($additionalPaymentInformation['card_id']) && $additionalPaymentInformation['card_id'] !== false
                && $this->tpay->getCardSaveEnabled()
            ) {
                $cardId = (int)$additionalPaymentInformation['card_id'];
                return $this->processSavedCardPayment($orderId, $cardId);
            } else {
                return $this->processNewCardPayment($orderId, $additionalPaymentInformation);
            }
        }
        $this->checkoutSession->unsQuoteId();

        return $this->_redirect(static::ERROR_PATH);
    }

    private function processSavedCardPayment($orderId, $cardId)
    {
        $customerTokens = $this->tokensService->getCustomerTokens($this->tpay->getCustomerId($orderId));
        $isValid = false;
        foreach ($customerTokens as $key => $value) {
            if ((int)$value['tokenId'] === $cardId) {
                //tokenId belongs to current customer
                $isValid = true;
                $token = $value['token'];
            }
        }
        if ($isValid) {
            try {
                $paymentResult = $this->cardTransactionModel->presale($this->tpayPaymentConfig['description'], $token);
                if (isset($paymentResult['sale_auth'])) {
                    $paymentResult = $this->cardTransactionModel->sale($paymentResult['sale_auth'], $token);
                }
                if (
                    (int)$paymentResult['result'] === 1
                    && isset($paymentResult['status'])
                    && $paymentResult['status'] === 'correct') {
                    $this->tpayService->setOrderStatus($orderId, $paymentResult, $this->tpay);
                    $this->tpayService->addCommentToHistory($orderId, 'Successful payment by saved card');

                    return $this->_redirect(static::SUCCESS_PATH);
                } elseif (isset($paymentResult['status']) && $paymentResult['status'] === 'declined') {
                    $this->tpayService->addCommentToHistory($orderId,
                        'Failed to pay by saved card, Elavon rejection code: ' . $paymentResult['reason']);
                } else {
                    $this->tpayService->addCommentToHistory($orderId,
                        'Failed to pay by saved card, error: ' . $paymentResult['err_desc']);
                }
            } catch (\Exception $e) {
                return $this->trySaleAgain($orderId);
            }
        }
        if (!$isValid) {
            $this->tpayService->addCommentToHistory($orderId, 'Attempt of payment by not owned card has been blocked!');
        }

        return $this->trySaleAgain($orderId);
    }

    /**
     * Redirect customer to tpay transaction panel and try to pay again
     * @param $orderId
     * @return \Magento\Framework\App\ResponseInterface
     */
    private function trySaleAgain($orderId)
    {
        $this->cardTransactionModel->setCardData(null);
        $result = $this->cardTransactionModel->registerSale(
            $this->tpayPaymentConfig['name'],
            $this->tpayPaymentConfig['email'],
            $this->tpayPaymentConfig['description']
        );
        if (isset($result['sale_auth'])) {
            $url = 'https://secure.tpay.com/cards?sale_auth=' . $result['sale_auth'];
            $this->tpayService->addCommentToHistory($orderId,
                'Customer has been redirected to tpay.com transaction panel. Transaction link ' . $url);
            $this->addToPaymentData($orderId, 'transaction_url', $url);
            return $this->_redirect($url);
        }

        return $this->_redirect(static::ERROR_PATH);
    }

    private function addToPaymentData($orderId, $key, $value)
    {
        $payment = $this->tpayService->getPayment($orderId);
        $paymentData = $payment->getData();
        $paymentData['additional_information'][$key] = $value;
        $payment->setData($paymentData)->save();
    }

    private function processNewCardPayment($orderId, $additionalPaymentInformation)
    {
        $saveCard = isset($additionalPaymentInformation['card_save']) && $this->tpay->getCardSaveEnabled() ?
            (bool)$additionalPaymentInformation['card_save'] : false;
        if ($saveCard === true) {
            $this->cardTransactionModel->setOneTimer(false);
        }
        try {
            $result = $this->createNewCardPayment($additionalPaymentInformation);
        } catch (\Exception $e) {
            return $this->trySaleAgain($orderId);
        }
        if (isset($result['3ds_url'])) {
            $url3ds = $result['3ds_url'];
            $this->tpayService->addCommentToHistory($orderId, '3DS Transaction link ' . $url3ds);
            $this->addToPaymentData($orderId, 'transaction_url', $url3ds);

            return $this->_redirect($url3ds);

        } else {
            if (isset($result['status']) && $result['status'] === 'correct') {
                $this->validateNon3dsSign($result);
                $this->tpayService->setOrderStatus($orderId, $result, $this->tpay);
            }

            if (isset($result['cli_auth'], $result['card']) && !$this->tpay->isCustomerGuest($orderId)) {
                $this->tokensService
                    ->setCustomerToken(
                        $this->tpay->getCustomerId($orderId),
                        $result['cli_auth'],
                        $result['card'],
                        $additionalPaymentInformation['card_vendor']
                    );
            }

            return (int)$result['result'] === 1 && isset($result['status']) && $result['status'] === 'correct' ?
                $this->_redirect(static::SUCCESS_PATH) :
                $this->trySaleAgain($orderId);
        }
    }

    /**
     * Create  card payment for transaction data
     *
     * @param array $additionalPaymentInformation
     * @return array
     */
    private function createNewCardPayment(array $additionalPaymentInformation)
    {
        $cardData = str_replace(' ', '+', $additionalPaymentInformation['card_data']);

        return $this->cardTransactionModel->registerSale(
            $this->tpayPaymentConfig['name'],
            $this->tpayPaymentConfig['email'],
            $this->tpayPaymentConfig['description'],
            $cardData
        );
    }

    private function validateNon3dsSign($tpayResponse)
    {
        $testMode = isset($tpayResponse['test_mode']) ? '1' : '';
        $cliAuth = isset($tpayResponse['cli_auth']) ? $tpayResponse['cli_auth'] : '';
        $localHash = hash(
            $this->tpay->getHashType(),
            $testMode.
            $tpayResponse['sale_auth'].
            $cliAuth.
            $tpayResponse['card'].
            $this->tpayPaymentConfig['currency'].
            $this->tpayPaymentConfig['amount'].
            $tpayResponse['date'].
            $tpayResponse['status'].
            $this->tpay->getVerificationCode()
        );
        if ($tpayResponse['sign'] !== $localHash) {
            throw new \Exception('Card payment - invalid checksum');
        }
    }

}
