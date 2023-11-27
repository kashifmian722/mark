<?php

namespace Webkul\MultiVendor\Event;

use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;
use Webkul\MultiVendor\Event\MarketplaceEventLifeCycle;

class MarketplaceEvent extends Event
{
    const MARKETPLACE_EVENT_ORDER_LIST = 'list';
    const MARKETPLACE_EVENT_ORDER_UPDATE = 'updateOrder';
    const MARKETPLACE_STORE_GET_PROFILE = 'getStoreProfile';
    const MARKETPLACE_SELLER_UPDATE_PROFILE = 'updateProfile';
    const MARKETPLACE_SELLER_ADD_REVIEW = 'submitStoreReview';

    const MARKETPLACE_PRODUCT_LIST = 'marketplaceProducts';
    const MARKETPLACE_PRODUCT_CREATE = 'createMarketplaceProduct';
    const MARKETPLACE_PRODUCT_UPDATE = 'updateMarketplaceProduct';
    const MARKETPLACE_PRODUCT_DELETE = 'deleteMarketplaceProduct';
    const MARKETPLACE_PRODUCT_VARIANT_UPDATE = 'updateProductVariant';
    const MARKETPLACE_PRODUCT_VARIANT_DELETE = 'deleteProductVariant';
    const MARKETPLACE_PRODUCT_VARIANT_LIST_UPDATE = 'updateProductVariantsList';
    const MARKETPLACE_REPORT_ORDER = 'marketplaceReportOrder';
    const MARKETPLACE_REPORT_SALES = 'marketplaceReportSales';
    const MARKETPLACE_REPORT_BUYERS = 'marketplaceReportBuyers';
    const MARKETPLACE_REPORT_STOCK = 'marketplaceReportStock';

    private $event;
    private $eventData = [];
    private $eventResponse = [];

    public function __construct($event)
    {
        $this->setEvent($event);
    }

    public static function getEventCycle($marketplaceEventName)
    {
        // get all constants inside MarketplaceEvent class
        $marketplaceEventClass = new \ReflectionClass(static::class);
        $routines = $marketplaceEventClass->getConstants();

        $eventLifeCycle = [];
        foreach($routines as $routine) {
            $eventLifeCycle[$routine] = MarketplaceEventLifeCycle::getEventLifeCycle(
                $marketplaceEventName, $routine
            );
        }

        foreach ($eventLifeCycle as $eventName => $eventSubRoutine) {
            if (empty($eventSubRoutine)) {
                unset($eventLifeCycle[$eventName]);
            }
        }

        return !empty($eventLifeCycle) ? $eventLifeCycle : [];
    }

    private function setEvent($event)
    {
        $this->event = $event;

        return $this;
    }

    private function getEvent()
    {
        return $this->event;
    }

    public function setEventData($eventData)
    {
        foreach (array_keys($eventData) as $dataKey) {
            $this->eventData[$dataKey] = $eventData[$dataKey];
        }

        return $this;
    }

    public function getEventData()
    {
        return $this->eventData;
    }

    public function setEventResponse($eventResponse)
    {
        foreach (array_keys($eventResponse) as $responseKey) {
            $this->eventResponse[$responseKey] = $eventResponse[$responseKey];
        }

        return $this;
    }

    public function getEventResponse()
    {
        return $this->eventResponse;
    }

    public function getContext()
    {
        return Context::createDefaultContext();
    }
}
