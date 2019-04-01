<?php
/**
 *
 * @category    payment gateway
 * @package     Tpaycom_Magento2.1
 * @author      tpay.com
 * @copyright   (https://tpay.com)
 */

namespace tpaycom\magento2cards\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Model\MethodInterface;
use tpaycom\magento2cards\Api\TpayCardsInterface;

use Magento\Payment\Helper\Data as PaymentHelper;
use tpaycom\magento2cards\Service\TpayTokensService;

/**
 * Class TpayCardsConfigProvider
 *
 * @package tpaycom\magento2cards\Model
 */
class TpayCardsConfigProvider implements ConfigProviderInterface
{
    /**
     * @var Repository
     */
    protected $assetRepository;

    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /**
     * @var TpayCardsInterface
     */
    protected $paymentMethod;

    /**
     * @var TpayTokensService
     */
    protected $tokensService;

    /**
     * TpayCardsConfigProvider constructor.
     *
     * @param PaymentHelper $paymentHelper
     * @param Repository $assetRepository
     * @param TpayTokensService $tokensService
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        Repository $assetRepository,
        TpayTokensService $tokensService
    ) {
        $this->assetRepository = $assetRepository;
        $this->paymentHelper = $paymentHelper;
        $this->tokensService = $tokensService;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $tpay = $this->getPaymentMethodInstance();
        $customerTokensData = [];
        if ($tpay->getCardSaveEnabled()) {
            $customerTokens = $this->tokensService->getCustomerTokens($tpay->getCheckoutCustomerId());
            foreach ($customerTokens as $key => $value) {
                $customerTokensData[] = [
                    'cardShortCode' => $value['cardShortCode'],
                    'id'            => $value['tokenId'],
                    'vendor'        => $value['vendor'],
                ];
            }
        }
        $config = [
            'tpaycards' => [
                'payment' => [
                    'tpayLogoUrl'        => $this->generateURL('tpaycom_magento2cards::images/logo_tpay.png'),
                    'getTpayLoadingGif'  => $this->generateURL('tpaycom_magento2cards::images/loading.gif'),
                    'getRSAkey'          => $tpay->getRSAKey(),
                    'fetchJavaScripts'   => $this->fetchJavaScripts(),
                    'addCSS'             => $this->createCSS('tpaycom_magento2cards::css/tpaycards.css'),
                    'redirectUrl'        => $tpay->getPaymentRedirectUrl(),
                    'isCustomerLoggedIn' => $tpay->isCustomerLoggedIn(),
                    'customerTokens'     => $customerTokensData,
                    'isSavingEnabled'    => $tpay->getCardSaveEnabled(),
                ],
            ],
        ];

        return $tpay->isAvailable() ? $config : [];
    }

    /**
     * @return TpayCardsInterface|MethodInterface
     */
    protected function getPaymentMethodInstance()
    {
        if (null === $this->paymentMethod) {
            $this->paymentMethod = $this->paymentHelper->getMethodInstance(TpayCardsInterface::CODE);
        }

        return $this->paymentMethod;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public function generateURL($name)
    {
        return $this->assetRepository->createAsset($name)->getUrl();
    }

    public function fetchJavaScripts()
    {
        $script[] = 'tpaycom_magento2cards::js/jquery.payment.min.js';
        $script[] = 'tpaycom_magento2cards::js/jquery.min.js';
        $script[] = 'tpaycom_magento2cards::js/jsencrypt.min.js';
        $script[] = 'tpaycom_magento2cards::js/string_routines.js';
        $script[] = 'tpaycom_magento2cards::js/tpayCards.js';
        $script[] = 'tpaycom_magento2cards::js/renderSavedCards.js';
        $scripts = '';
        foreach ($script as $key => $value) {
            $scripts .= $this->createScript($value);
        }

        return $scripts;
    }

    /**
     * @param string $script
     *
     * @return string
     */
    public function createScript($script)
    {
        return "
            <script type=\"text/javascript\">
                require(['jquery'], function ($) {
                    $.getScript('{$this->generateURL($script)}');

                });
            </script>";
    }

    /**
     * @param string $css
     *
     * @return string
     */
    public function createCSS($css)
    {
        return "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$this->generateURL($css)}\">";
    }
}
