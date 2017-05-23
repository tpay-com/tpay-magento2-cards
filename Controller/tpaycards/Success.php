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

/**
 * Class Success
 *
 * @package tpaycom\magento2cards\Controller\tpaycardscards
 */
class Success extends Action
{
    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $this->messageManager->addSuccessMessage(__('Dziękujemy za dokonanie płatności.'));

        return $this->_redirect('checkout/cart');
    }
}
