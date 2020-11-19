<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE_ABC
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_MpWebhook
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\MpWebhook\Observer;

use Exception;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Event\Observer;
use Magento\Newsletter\Model\Subscriber as SubscriberMagento;
use Magento\Store\Model\Store;
use Mageplaza\Webhook\Model\Config\Source\Schedule;
use Mageplaza\Webhook\Observer\Subscriber as AbstractSubscriber;

/**
 * Class Subscriber
 * @package Mageplaza\MpWebhook\Observer
 */
class Subscriber extends AbstractSubscriber
{
    public function execute(Observer $observer)
    {
        $item             = $observer->getEvent()->getSubscriber();
        $subscriberStatus = $item->getSubscriberStatus();

        if ($subscriberStatus === SubscriberMagento::STATUS_UNSUBSCRIBED) {
            return $this;
        }

        $user         = $explode = explode("@",$item->getSubscriberEmail());
        $customerData = [
            'email'      => $item->getSubscriberEmail(),
            'website_id' => $this->storeManager->getStore()->getWebsiteId(),
            'group_id'   => 1,
            'firstname'  => $user[0],
            'lastname'   => $user[0]
        ];

        $customer                  = $this->helper->createObject(CustomerRepositoryInterface::class);
        $customerDataFactory       = $this->helper->createObject(CustomerInterfaceFactory::class);
        $dataObjectHelper          = $this->helper->createObject(DataObjectHelper::class);
        $customerAccountManagement = $this->helper->createObject(AccountManagementInterface::class);

        try {
            $customerObject = $customer->get($item->getSubscriberEmail());
            if ($customerObject) {
                $subscriber      = $this->helper->createObject(SubscriberMagento::class);
                $checkSubscriber = $subscriber->loadByEmail($item->getSubscriberEmail());
                if ($checkSubscriber->isSubscribed()) {
                    return $this;
                }
            }
        } catch (Exception $e) {
            $customer = $customerDataFactory->create();
            $dataObjectHelper->populateWithArray(
                $customer,
                $customerData,
                CustomerInterface::class
            );
            $customerObject = $customerAccountManagement->createAccount($customer);
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $tableName = $resource->getTableName('newsletter_subscriber'); //gives table name with prefix
            $sql = "Update " . $tableName . " Set subscriber_status = 1, customer_id = " . $customerObject->getId() . " where `subscriber_email` = '" . $item->getSubscriberEmail() . "'";
            $connection->query($sql);
            return $this;
        }

        if ($this->helper->getCronSchedule() !== Schedule::DISABLE) {
            $hookCollection = $this->hookFactory->create()->getCollection()
                ->addFieldToFilter('hook_type', $this->hookType)
                ->addFieldToFilter('status', 1)
                ->addFieldToFilter('store_ids', [
                    ['finset' => Store::DEFAULT_STORE_ID],
                    ['finset' => $this->helper->getItemStore($item)]
                ])
                ->setOrder('priority', 'ASC');
            if ($hookCollection->getSize() > 0) {
                $schedule = $this->scheduleFactory->create();
                $data     = [
                    'hook_type' => $this->hookType,
                    'event_id'  => $item->getId(),
                    'status'    => '0'
                ];

                try {
                    $schedule->addData($data);
                    $schedule->save();
                } catch (Exception $exception) {
                    $this->messageManager->addError($exception->getMessage());
                }
            }
        } else {
            $this->helper->send($item, $this->hookType);
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $tableName = $resource->getTableName('newsletter_subscriber'); //gives table name with prefix
            $sql = "Update " . $tableName . " Set subscriber_status = 1 where `subscriber_email` = '" . $item->getSubscriberEmail() . "'";
            $connection->query($sql);
        }

        return $this;
    }
}
