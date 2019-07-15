<?php
/**
 *
 * @category    payment gateway
 * @package     Tpaycom_Magento2.3
 * @author      Tpay.com
 * @copyright   (https://tpay.com)
 */

namespace tpaycom\magento2cards\Block\Payment\tpaycards;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use tpaycom\magento2cards\Api\TpayCardsInterface;
use tpaycom\magento2cards\Block\Payment\tpaycards\Redirect\Form;

/**
 * Class Redirect
 *
 * @package tpaycom\magento2cards\Block\Payment\tpaycards
 */
class Redirect extends Template
{
    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var array
     */
    protected $additionalPaymentInformation = [];

    /**
     * @var TpayCardsInterface
     */
    protected $tpay;

    /**
     * {@inheritdoc}
     *
     * @param TpayCardsInterface $tpayModel
     * @param array         $data
     */
    public function __construct(
        Context $context,
        TpayCardsInterface $tpayModel,
        array $data = []
    ) {
        $this->tpay        = $tpayModel;

        parent::__construct(
            $context,
            $data
        );
    }

    /**
     * @param int $orderId
     *
     * @return $this
     */
    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;

        return $this;
    }

    /**
     * @param array $additionalPaymentInformation
     *
     * @return $this
     */
    public function setAdditionalPaymentInformation(array $additionalPaymentInformation)
    {
        $this->additionalPaymentInformation = $additionalPaymentInformation;

        return $this;
    }

    /**
     * Get form Html
     *
     * @return string
     */
    public function getFormHtml()
    {
        /** @var Form $formBlock */
        $formBlock = $this->getChildBlock('form');

        $formBlock
            ->setOrderId($this->orderId)
         //   ->setAction($this->tpaycards->getRedirectURL())
            ->setTpayData($this->tpay->getTpayFormData($this->orderId))
            ->setAdditionalInformation($this->additionalPaymentInformation);

        return $formBlock->toHtml();
    }

    /**
     * {@inheritdoc}
     */
    protected function _construct()
    {
        $this->setTemplate('tpaycom_magento2cards::redirect.phtml');

        parent::_construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function _toHtml()
    {
        if ($this->orderId === null) {
            return false;
        }

        $this->addChild('form', 'tpaycom\magento2cards\Block\Payment\tpaycards\Redirect\Form');

        return parent::_toHtml();
    }
}
