<?php
/**
 *
 * @category    payment gateway
 * @package     Tpaycom_Magento2.1
 * @author      tpay.com
 * @copyright   (https://tpay.com)
 */

namespace tpaycom\magento2cards\Model\Sales;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderRepository as MagentoOrderRepository;
use tpaycom\magento2cards\Api\Sales\CardsOrderRepositoryInterface;

/**
 * Class CardsOrderRepository
 *
 * @package tpaycom\magento2cards\Model\Sales
 */
class CardsOrderRepository extends MagentoOrderRepository implements CardsOrderRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getByIncrementId($incrementId)
    {
        if (!$incrementId) {
            throw new InputException(__('Id required'));
        }

        /** @var OrderInterface $entity */
        $entity = $this->metadata->getNewInstance()->loadByIncrementId($incrementId);

        if (!$entity->getEntityId()) {
            throw new NoSuchEntityException(__('Requested entity doesn\'t exist'));
        }

        return $entity;
    }
}
