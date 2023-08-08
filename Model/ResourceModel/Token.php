<?php

namespace tpaycom\magento2cards\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Token extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('tpay_credit_cards', 'id');
    }
}
