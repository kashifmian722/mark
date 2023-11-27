<?php

namespace Webkul\MultiVendor\EventSubscriber;

use Webkul\MultiVendor\Event\MarketplaceEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Context;

class OrderSubscriber implements EventSubscriberInterface
{
    private $container;
    private static $eventName;

    public function __construct(
        String $eventName,
        ContainerInterface $container
    ) {
        self::$eventName = $eventName;
        $this->container = $container;
    }

    public static function getSubscribedEvents()
    {
        // return the subscribed events, their methods and priorities
        return MarketplaceEvent::getEventCycle(self::$eventName);
    }

    public function fetchOrderList(MarketplaceEvent $marketplaceEvent)
    {
        $eventData = $marketplaceEvent->getEventData();
        $languageId = $eventData['salesChannel']->getSaleschannel()->getLanguageId();
      
        $params = $eventData['params'];
        $customerId = $eventData['customerId'];

        $dataFormatter = $this->container->get('marketplace.data.format');
        $storefrontHelperService = $this->container->get('storefront.helper');

        $marketplaceSellerOrdersCollection = $storefrontHelperService->fetchSellerOrders($customerId, $languageId, $eventData['salesChannel']->getSaleschannel()->getId(), $params);
        // dhl shipping plugin
        $dhlPlugin = false;
        $dhlShippingPlugin = $this->container->get('plugin.repository')->search((new Criteria())->addFilter(new EqualsFilter('name','WebkulMPDhlShipping'))->addFilter(new EqualsFilter('active',1)), Context::createDefaultContext());
        if ($dhlShippingPlugin->getTotal() > 0) {
            $dhlPlugin = true;
        }
        // formatted order collection
        $ordersCollection = [];
        foreach ($marketplaceSellerOrdersCollection['orders'] as $order) {
            
            $shippingMethodId = $order->get('order')->getDeliveries()->first()->getShippingMethodId();
            $paymentMethodName = $order->get('order_line_item')->getOrder()->getTransactions()->first()->getPaymentMethod()->getTranslated()['name'];
            
            $shippingMethodName = $order->get('order')->getDeliveries()->first()->getShippingMethod()->getTranslated()['name'];
            $orderLineItem = $order->get('order_line_item');
            $shopwareOrder = $orderLineItem->getOrder();
            // order customer
            $orderCustomer = $shopwareOrder->getOrderCustomer();
            
            // formatted order line item
            $formattedOrderLineItem = $dataFormatter->formatOrderLineItem($orderLineItem);
            
            // order transaction details
            $orderTransaction = $shopwareOrder->getTransactions()->first();
            $paymentMethod = $orderTransaction->get('paymentMethod')->get('name');

            $paymentState = $orderTransaction->get('stateMachineState');
            $paymentStatus = $paymentState->get('name');

            // order state
            $orderState = $shopwareOrder->get('stateMachineState');
            $orderStatus = $orderState->get('name');
            $orderStateId = $orderState->get('_uniqueIdentifier');
            $marketplaceOrderId = $order->get('id');

            $commissionRate = $storefrontHelperService->getSystemConfigurationValue('WebkulMVMarketplace.config.commission', $eventData['salesChannel']);
            $manageOrderStatus = $storefrontHelperService->getSystemConfigurationValue('WebkulMVMarketplace.config.manageOrderStatus', $eventData['salesChannel']);
            // get dhl shipping label
            
            if ($dhlShippingPlugin->getTotal() > 0) {
                $dhlLabelData = $this->container->get('marketplace_seller_shipping_label.repository')->search((new Criteria())->addFilter(new EqualsFilter('orderId',$order->get('orderId')))->addFilter(new EqualsFilter('productId',$orderLineItem->get('identifier'))),Context::createDefaultContext());
                if ($dhlLabelData->getTotal() > 0) {
                    $dhlLabel = true;
                } else {
                    $dhlLabel = false;
                }
                $shippingMethodTag = $this->container->get('shipping_method.repository')->search((new Criteria())->addFilter(new EqualsFilter('id',$shippingMethodId))->addFilter(new EqualsFilter('tags.name','MP-DHL'))->addAssociation('tags'), Context::createDefaultContext());
                
                if ($shippingMethodTag->getTotal() > 0) {
                    $dhlShipping = true;
                } else{
                    $dhlShipping = false;
                }
            }
            $orderDetails = [
                'orderAddress'=> $order->get('order')->getAddresses(),
                'currencySymbol' => $order->get('symbol'),
                'orderStatus' => $orderStatus,
                'lineItemStateId'=> $order->get('orderStatus'),
                'orderStateId' => $orderStateId,
                'marketplaceOrderId'=> $marketplaceOrderId,
                'id' => $order->get('orderId'),
                'paymentMethod' => $paymentMethod,
                'earning' => $order->get('earning'),
                'paymentStatus' => $paymentStatus,
                'commission' => $order->get('commission'),
                'customerEmail' => $orderCustomer->get('email'),
                'productId' => $orderLineItem->get('identifier'),
                'orderNumber' => $shopwareOrder->get('orderNumber'),
                'date' => $shopwareOrder->get('orderDateTime')->format('d/m/Y'),
                'totalAmount' => $orderTransaction->get('amount')->getTotalPrice(),
                'customerName' => $orderCustomer->get('firstName') . ' ' . $orderCustomer->get('lastName'),
                'shippingMethodName' => $shippingMethodName,
                'paymentMethodName' => $paymentMethodName,
                'shippingCost' => $shopwareOrder->getShippingCosts()->getTotalPrice()
            ];
            if (isset($dhlLabel)) {
                $orderDetails['dhlLabel'] = $dhlLabel;
            }
            if (isset($dhlShipping)) {
                $orderDetails['dhlShipping'] = $dhlShipping;
            }
            
            $orderDetails = array_merge($orderDetails, $formattedOrderLineItem);
            array_push($ordersCollection, $orderDetails);
        }

        // sort orders according to order number
        usort($ordersCollection, function ($order1, $order2) {
            $order1 = $order1['orderNumber'];
            $order2 = $order2['orderNumber'];
            if ($order1 == $order2) {
                return 0;
            }
            elseif ($order1 > $order2) {
                return -1;
            }
            else{
                return 1;
            }

            //return ($order1 == $order2) ? 0 : ($order1 > $order2) ? -1 : 1;
        });

        $marketplaceEvent->setEventResponse([
            'orders'=> $ordersCollection,
            'total' => $marketplaceSellerOrdersCollection['total'],
            'orderState'=> $marketplaceSellerOrdersCollection['orderState'],
            'manageOrderStatus' => $manageOrderStatus,
            'dhlPlugin' => $dhlPlugin
        ]);

        return;
    }
    public function updateOrder(MarketplaceEvent $marketplaceEvent) 
    {

        $orderParams = $marketplaceEvent->getEventData()['params'];
        $salesChannelContext = $marketplaceEvent->getEventData()['salesChannelContext'];
        $salesChannelId = $salesChannelContext->getSalesChannel()->getDomains()->first()->getSalesChannelId();
        
        $storefrontHelperService = $this->container->get('storefront.helper');
        $storefrontHelperService->updateSellerOrder($orderParams);
        $storefrontHelperService->sendMailOnUpdateOrder($orderParams, $salesChannelId);
        $response = [
            'status' => true,
        ];

        $marketplaceEvent->setEventResponse($response);
        return;
    }
}

?>
