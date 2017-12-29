<?php
/**
 *
 * @category    payment gateway
 * @package     Tpaycom_Magento2.1
 * @author      tpay.com
 * @copyright   (https://tpay.com)
 */

namespace tpaycom\magento2cards\Controller\tpaycards;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Model\Context as ModelContext;
use tpaycom\magento2cards\Api\TpayCardsInterface;
use tpaycom\magento2cards\lib\PaymentCardFactory;
use tpaycom\magento2cards\lib\ResponseFields;
use tpaycom\magento2cards\lib\Validate;
use tpaycom\magento2cards\Model\ApiProvider;
use tpaycom\magento2cards\Service\TpayService;
use tpaycom\magento2cards\Service\TpayTokensService;
use Magento\Framework\Registry;

/**
 * Class CardPayment
 *
 * @package tpaycom\magento2cards\Controller\tpaycardscards
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

    private $validate;
    private $apiFactory;
    private $paymentCardFactory;
    private $registry;
    private $modelContext;
    private $tokensService;

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
        Validate $validate,
        PaymentCardFactory $paymentCardFactory,
        ModelContext $modelContext,
        Registry $registry
    ) {
        $this->tpay = $tpayModel;
        $this->tpayService = $tpayService;
        $this->checkoutSession = $checkoutSession;
        $this->validate = $validate;
        $this->paymentCardFactory = $paymentCardFactory;
        $this->modelContext = $modelContext;
        $this->registry = $registry;
        $this->tokensService = new TpayTokensService($this->modelContext, $this->registry);
        parent::__construct($context);
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $orderId = $this->checkoutSession->getLastRealOrderId();
        $this->apiFactory = (new ApiProvider($this->tpay, $this->paymentCardFactory))->getTpayCardAPI();
        if ($orderId) {
            $payment = $this->tpayService->getPayment($orderId);
            $paymentData = $payment->getData();
            $this->tpayService->setOrderStatePendingPayment($orderId);
            $additionalPaymentInformation = $paymentData['additional_information'];

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
        $data = $this->tpay->getTpayFormData($orderId);
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
                $paymentResult = $this->apiFactory->completeSale($token, $data['opis'], $data['kwota'],
                    $data['currency'], $data['crc'], $data['jezyk'], $data['module']);
                if ((int)$paymentResult['result'] === 1
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
                return $this->trySaleAgain($data, $orderId);
            }
        }
        if (!$isValid) {
            $this->tpayService->addCommentToHistory($orderId, 'Attempt of payment by not owned card has been blocked!');
        }
        return $this->trySaleAgain($data, $orderId);
    }

    /**
     * Redirect customer to tpay transaction panel and try to pay again
     * @param array $data
     * @param $orderId
     * @param bool $saveCard
     * @return \Magento\Framework\App\ResponseInterface
     */
    private function trySaleAgain($data, $orderId, $saveCard = false)
    {
        $result = $this->apiFactory->registerSale($data['nazwisko'], $data['email'], $data['opis'], $data['kwota'],
            $data['currency'], $data['crc'], !$saveCard, $data['jezyk'], true, $data['pow_url'], $data['pow_url_blad'],
            $data['module']);
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
        $paymentCardFactory = (new ApiProvider($this->tpay,
            $this->paymentCardFactory))->getTpayPaymentCardFactory();
        $localData = $this->tpay->getTpayFormData($orderId);
        try {
            $result = $this->createNewCardPayment($orderId, $additionalPaymentInformation, $saveCard);
        } catch (\Exception $e) {
            return $this->trySaleAgain($localData, $orderId, $saveCard);
        }
        if (isset($result[ResponseFields::URL3DS])) {
            $url3ds = $result[ResponseFields::URL3DS];
            $this->tpayService->addCommentToHistory($orderId, '3DS Transaction link ' . $url3ds);
            $this->addToPaymentData($orderId, 'transaction_url', $url3ds);

            return $this->_redirect($url3ds);

        } else {
            if (isset($result[ResponseFields::STATUS]) && (int)$result[ResponseFields::STATUS] === 'correct') {
                $paymentCardFactory->validateNon3dsSign($result['sign'], isset($result['test_mode']) ? '1' : '',
                    $result['sale_auth'], '', $result['card'], $localData['kwota'], $result['date'],
                    $localData['currency']);
            }
            $this->tpayService->setOrderStatus($orderId, $result, $this->tpay);

            if (isset($result['cli_auth']) && isset($result['card']) && !$this->tpay->isCustomerGuest($orderId)) {
                $this->tokensService
                    ->setCustomerToken($this->tpay->getCustomerId($orderId), $result['cli_auth'], $result['card'],
                        $additionalPaymentInformation['card_vendor']);
            }
            return (int)$result[ResponseFields::RESULT] === 1 && $result[ResponseFields::STATUS] === 'correct' ?
                $this->_redirect(static::SUCCESS_PATH) :
                $this->trySaleAgain($localData, $orderId, $saveCard);
        }
    }

    /**
     * Create  card payment for transaction data
     *
     * @param int $orderId
     * @param array $additionalPaymentInformation
     * @param $saveCard
     * @return array
     */
    private function createNewCardPayment($orderId, array $additionalPaymentInformation, $saveCard)
    {
        $data = $this->tpay->getTpayFormData($orderId);
        $cardData = str_replace(' ', '+', $additionalPaymentInformation['card_data']);
        unset($additionalPaymentInformation['card_data']);
        $data = array_merge($data, $additionalPaymentInformation);

        return $this->apiFactory->secureSale($data['nazwisko'], $data['email'], $data['opis'], $data['kwota'],
            $cardData, $data['currency'], $data['crc'], !$saveCard, $data['jezyk'], true, $data['pow_url'],
            $data['pow_url_blad'], $data['module']
        );
    }

}
