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
use Magento\Framework\DataObject;
use tpaycom\magento2cards\Api\TpayCardsInterface;
use tpaycom\magento2cards\lib\CardAPI;
use tpaycom\magento2cards\lib\PaymentCardFactory;
use tpaycom\magento2cards\lib\ResponseFields;
use tpaycom\magento2cards\lib\Validate;
use tpaycom\magento2cards\Model\ApiProvider;
use tpaycom\magento2cards\Model\CardsTransactionFactory;
use tpaycom\magento2cards\Model\CardTransaction;
use tpaycom\magento2cards\Service\TpayService;
use Zend\Http\Header\Location;
use Magento\Sales\Model\Order;

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
        PaymentCardFactory $paymentCardFactory
    ) {
        $this->tpay = $tpayModel;
        $this->tpayService = $tpayService;
        $this->checkoutSession = $checkoutSession;
        $this->validate = $validate;
        $this->paymentCardFactory = $paymentCardFactory;
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
            $paymentData = $this->tpayService->getPaymentData($orderId);
            $this->tpayService->setOrderStatePendingPayment($orderId);
            $additionalPaymentInformation = $paymentData['additional_information'];
            $paymentCardFactory = (new ApiProvider($this->tpay, $this->paymentCardFactory))->getTpayPaymentCardFactory();
            $result = $this->makeCardPayment($orderId, $additionalPaymentInformation);
            $this->checkoutSession->unsQuoteId();

            if (isset($result[ResponseFields::URL3DS])) {
                $url3ds = $result[ResponseFields::URL3DS];
                $this->tpayService->addCommentToHistory($orderId, '3DS Transaction link ' . $url3ds);
                return $this->_redirect($url3ds);

            } else {
                $localData = $this->tpay->getTpayFormData($orderId);

                if (isset($result[ResponseFields::STATUS]) && (int)$result[ResponseFields::STATUS] === 'correct') {
                    $paymentCardFactory->validateNon3dsSign($result['sign'], isset($result['test_mode']) ? '1' : '',
                        $result['sale_auth'], '', $result['card'], $localData['kwota'], $result['date'],
                        $localData['currency']);
                }
                $this->tpayService->setOrderStatus($orderId, $result);

                return ((int)$result[ResponseFields::RESULT] === 1 && $result[ResponseFields::STATUS] === 'correct') ?
                    $this->_redirect('magento2cards/tpaycards/success') : $this->_redirect(static::ERROR_PATH);
            }
        }
        return $this->_redirect(static::ERROR_PATH);
    }

    /**
     * Create  card payment for transaction data
     *
     * @param int $orderId
     * @param array $additionalPaymentInformation
     *
     * @return bool
     */
    protected function makeCardPayment($orderId, array $additionalPaymentInformation)
    {
        $data = $this->tpay->getTpayFormData($orderId);
        $cardData = str_replace(' ', '+', $additionalPaymentInformation['card_data']);
        unset($additionalPaymentInformation['card_data']);
        $data = array_merge($data, $additionalPaymentInformation);

        return $this->apiFactory->secureSale($data['nazwisko'], $data['email'], $data['opis'], $data['kwota'],
            $cardData, $data['currency'],
            $data['crc'], true, $data['jezyk'], true, $data['pow_url'], $data['pow_url_blad']
        );
    }

}
