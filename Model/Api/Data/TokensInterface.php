<?php

namespace tpaycom\magento2cards\Model\Api\Data;

interface TokensInterface
{
    public function setCustomerId($id);

    public function getToken($customerId);

    public function setToken($token);

    public function setShortCode($shortCode);

    public function setCreationTime();
}
