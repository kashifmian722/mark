<?php

namespace Webkul\MultiVendor\Event;

use Webkul\MultiVendor\Event;

class MarketplaceEventLifeCycle
{
    public static function getEventLifeCycle($event, $eventTask)
    {
        
        switch ($event) {
            case 'marketplace.order':
                switch ($eventTask) {
                    case Event\MarketplaceEvent::MARKETPLACE_EVENT_ORDER_LIST:
                        $methodsCollection = [
                            ['fetchOrderList', 1],
                        ];
                        break;
                    case Event\MarketplaceEvent::MARKETPLACE_EVENT_ORDER_UPDATE:
                        $methodsCollection = [
                            [Event\MarketplaceEvent::MARKETPLACE_EVENT_ORDER_UPDATE, 1],
                        ];
                        break;

                    default:
                        break;
                }
                break;

            case 'marketplace.profile':
                switch ($eventTask) {
                    case Event\MarketplaceEvent::MARKETPLACE_STORE_GET_PROFILE:
                        $methodsCollection = [
                            [Event\MarketplaceEvent::MARKETPLACE_STORE_GET_PROFILE, 1],
                        ];
                        break;

                    case Event\MarketplaceEvent::MARKETPLACE_SELLER_UPDATE_PROFILE:
                        $methodsCollection = [
                            ['updateSellerProfile', 1],
                        ];
                        break;

                    case Event\MarketplaceEvent::MARKETPLACE_SELLER_ADD_REVIEW:
                        $methodsCollection = [
                            [Event\MarketplaceEvent::MARKETPLACE_SELLER_ADD_REVIEW, 1],
                        ];
                        break;
                    // case Event\MarketplaceEvent::MARKETPLACE_SELLER_GET_REVIEW:
                    //     $methodsCollection = [
                    //         [EVENT\MarketplaceEvent::MARKETPLACE_SELLER_GET_REVIEW, 1],
                    //     ];
                    //     break;
                    default:
                        break;
                }
                break;

            case 'marketplace.product':
                switch ($eventTask) {
                    case Event\MarketplaceEvent::MARKETPLACE_PRODUCT_LIST:
                        $methodsCollection = [
                            ['fetchSellerProducts', 1],
                        ];
                        break;

                    case Event\MarketplaceEvent::MARKETPLACE_PRODUCT_CREATE:
                        $methodsCollection = [
                            [Event\MarketplaceEvent::MARKETPLACE_PRODUCT_CREATE, 1],
                        ];
                        break;

                    case Event\MarketplaceEvent::MARKETPLACE_PRODUCT_UPDATE:
                        $methodsCollection = [
                            [Event\MarketplaceEvent::MARKETPLACE_PRODUCT_UPDATE, 1],
                        ];
                        break;

                    case Event\MarketplaceEvent::MARKETPLACE_PRODUCT_VARIANT_UPDATE:
                        $methodsCollection = [
                            [Event\MarketplaceEvent::MARKETPLACE_PRODUCT_VARIANT_UPDATE, 1],
                        ];
                        break;

                    case Event\MarketplaceEvent::MARKETPLACE_PRODUCT_VARIANT_LIST_UPDATE:
                        $methodsCollection = [
                            [Event\MarketplaceEvent::MARKETPLACE_PRODUCT_VARIANT_LIST_UPDATE, 1],
                        ];
                        break;

                    case Event\MarketplaceEvent::MARKETPLACE_PRODUCT_VARIANT_DELETE:
                        $methodsCollection = [
                            [Event\MarketplaceEvent::MARKETPLACE_PRODUCT_VARIANT_DELETE, 1],
                        ];
                        break;
                    case Event\MarketplaceEvent::MARKETPLACE_PRODUCT_DELETE:
                        $methodsCollection = [
                            [Event\MarketplaceEvent::MARKETPLACE_PRODUCT_DELETE, 1],
                        ];
                        break;

                    default:
                        break;
                }
                break;
            case 'marketplace.report':
                switch($eventTask) {
                    case Event\MarketplaceEvent::MARKETPLACE_REPORT_ORDER;
                        $methodsCollection = [
                            [Event\MarketplaceEvent::MARKETPLACE_REPORT_ORDER, 1],
                        ];
                    break;
                }
            break;
            default:
                break;
        }

        return $methodsCollection ?? [];
    }
}

?>
