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
 * Class Error
 *
 * @package tpaycom\magento2cards\Controller\tpaycardscards
 */
class Error extends Action
{
    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $this->messageManager->addWarningMessage(__("There was an error during your payment."));

        return $this->_redirect('checkout/onepage/failure');
    }
}
