<?php

namespace Webkul\MultiVendor\EventSubscriber;

use Webkul\MultiVendor\Event\MarketplaceEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SalesSubscriber implements EventSubscriberInterface
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
    public function marketplaceReportOrder(MarketplaceEvent $marketplaceEvent)
    {
        
        $eventData = $marketplaceEvent->getEventData();
        
        $customerId = $eventData['customerId'];
        $storefrontHelperService = $this->container->get('storefront.helper');
        $totalOrder = $storefrontHelperService->getTodayOrder($customerId);
        $totalSales = $storefrontHelperService->getTodaySales($customerId);
        
        $totalBuyers = $storefrontHelperService->getNewBuyers($customerId);
        
        $lowStockCount = $storefrontHelperService->getLowStock($customerId, $eventData['salesChannelContext']); 
       
        $orderState = $storefrontHelperService->getOrderState($customerId);
        
        $orderGraphData = $storefrontHelperService->orderGraphData($customerId);
        $turnoverGraphData = $storefrontHelperService->turnoverGraphData($eventData);
        $response = [
            'status' => true,
            'totalOrder' => $totalOrder,
            'totalSale' => $totalSales,
            'totalBuyer' => $totalBuyers,
            'lowStockProductNo' => $lowStockCount,
            'orderStatus' => $orderState,
            'orderGraph' => $orderGraphData,
            'turnoverGraph' => $turnoverGraphData,
        ];

        $marketplaceEvent->setEventResponse($response);
        return;
    }
}