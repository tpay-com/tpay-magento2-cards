<?php

namespace tpaycom\magento2cards\Controller\tpaycards;

use Magento\Framework\App\Action\Action;

class Success extends Action
{
    public function execute()
    {
        $this->messageManager->addSuccessMessage(__('Thank you for your payment!'));

        return $this->_redirect('checkout/onepage/success');
    }
}
