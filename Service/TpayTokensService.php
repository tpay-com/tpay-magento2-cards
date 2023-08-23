<?php

namespace tpaycom\magento2cards\Service;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use tpaycom\magento2cards\Model\Tokens;

class TpayTokensService extends Tokens
{
    public function __construct(
        Context $context,
        Registry $registry,
        $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * @param string $customerId
     * @param string $token
     * @param string $shortCode
     * @param string $vendor
     */
    public function setCustomerToken($customerId, $token, $shortCode, $vendor)
    {
        $customerTokens = $this->getCustomerTokens($customerId);

        $exists = false;
        foreach ($customerTokens as $key => $value) {
            if ($value['token'] === $token) {
                $exists = true;
            }
        }

        if (!$exists) {
            $this->setCustomerId($customerId)
                ->setToken($token)
                ->setShortCode($shortCode)
                ->setVendor($vendor)
                ->setCreationTime()
                ->save();
        }
    }

    /**
     * @param mixed $customerId
     *
     * @return array<array<string>>
     */
    public function getCustomerTokens($customerId)
    {
        return $this->getToken($customerId);
    }

    /**
     * @param string $token
     *
     * @return $this
     */
    public function deleteCustomerToken($token)
    {
        return $this->deleteToken($token)->save();
    }
}
