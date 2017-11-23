<?php

namespace tpaycom\magento2cards\Model\ResourceModel\Token;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init('tpaycom\magento2cards\Model\Tokens',
            'tpaycom\magento2cards\Model\ResourceModel\Token');
    }
}
