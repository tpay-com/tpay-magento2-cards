<?php

namespace tpaycom\magento2cards\Controller\tpaycards;

use Magento\Framework\App\Action\Action;

/**
 * Class Error
 */
class Error extends Action
{
    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $this->messageManager->addWarningMessage(__('There was an error during your payment.'));

        return $this->_redirect('checkout/onepage/failure');
    }
}
