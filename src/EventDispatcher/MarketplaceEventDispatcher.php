<?php

namespace Webkul\MultiVendor\EventDispatcher;

use Webkul\MultiVendor\EventSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MarketplaceEventDispatcher extends EventDispatcher implements EventDispatcherInterface
{
    public function __construct($event, ContainerInterface $container)
    {
        switch ($event) {
            case 'marketplace.order':
                $this->addSubscriber(new EventSubscriber\OrderSubscriber($event, $container));
                break;
            case 'marketplace.profile':
                $this->addSubscriber(new EventSubscriber\ProfileSubscriber($event, $container));
                break;
            case 'marketplace.product':
                $this->addSubscriber(new EventSubscriber\ProductSubscriber($event, $container));
                break;
            case 'marketplace.report':
                $this->addSubscriber(new EventSubscriber\SalesSubscriber($event, $container));
                break;
            default:
                break;
        }
    }
}
