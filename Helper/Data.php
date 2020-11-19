<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
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

namespace Mageplaza\MpWebhook\Helper;

use Liquid\Template;
use Magento\Catalog\Model\Product;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order\Shipment;
use Mageplaza\Webhook\Helper\Data as AbstractData;

/**
 * Class Data
 * @package Mageplaza\MpWebhook\Helper
 */
class Data extends AbstractData
{
    public function generateLiquidTemplate($item, $templateHtml)
    {
        try {
            $template       = new Template;
            $filtersMethods = $this->liquidFilters->getFiltersMethods();

            $template->registerFilter($this->liquidFilters);
            $template->parse($templateHtml, $filtersMethods);

            if ($item instanceof Shipment) {
                $item->setData('customer_email', $item->getOrder()->getCustomerEmail());
                $item->setData('customer_firstname', $item->getOrder()->getCustomerFirstname());
                $item->setData('customer_lastname', $item->getOrder()->getCustomerLastname());
                $item->setData('shipment_status', $item->getOrder()->getStatus());
                $item->setData('customer_is_guest', $item->getOrder()->getCustomerIsGuest());
                $item->setData('visibleItems', $item->getOrder()->getAllVisibleItems());
            }

            if ($item instanceof Product) {
                $item->setStockItem(null);
            }

            if ($item instanceof Quote) {
                $abandonedCartUrl = $item->getStore()->getUrl(
                    'connector/email/getbasket',
                    ['quote_id' => $item->getId()]
                );
                $item->setData('abandonedcart_url', $abandonedCartUrl);
            }

            return $template->render([
                'item' => $item,
            ]);
        } catch (Exception $e) {
            $this->_logger->critical($e->getMessage());
        }

        return '';
    }
}
