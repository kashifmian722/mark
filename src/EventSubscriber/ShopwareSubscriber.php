<?php

namespace Webkul\MultiVendor\EventSubscriber;

use Exception;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;

class ShopwareSubscriber implements EventSubscriberInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public static function getSubscribedEvents()
    {
        return [
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'createCustomer',
            CustomerEvents::CUSTOMER_DELETED_EVENT => 'deleteCustomer',
            OrderEvents::ORDER_WRITTEN_EVENT => 'createMarketplaceOrder',
            AccountOrderPageLoadedEvent::class => 'orderPageLoadedEvent',
            CheckoutConfirmPageLoadedEvent::class => 'onloadcheckoutpage'
        ];
    }

    public function createCustomer(EntityWrittenEvent $event)
    {

        $context = $event->getContext();
        $storefrontHelper = $this->container->get('storefront.helper');
        $sellerRepository = $this->container->get('marketplace_seller.repository');

        $requestStack = $this->container->get('request_stack');
        $request = $requestStack->getCurrentRequest();

        $isSellerRequested = $request->request->get('sellerRequest');
        $isSellerRequested = $isSellerRequested === "true" ? true : false;

        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getExistence() !== null && $writeResult->getExistence()->exists()) {
                break;
            }

            if ($isSellerRequested) {

                $payload = $writeResult->getPayload();
                $salesChannelId = $payload['salesChannelId'];

                // check for auto approval of seller
                $autoApproveSeller = $storefrontHelper->getSystemConfigurationValue('WebkulMVMarketplace.config.sellerAutoApprove', $salesChannelId);
                $mpMailHelper = $this->container->get('mpmail.helper');
                $id = Uuid::randomHex();
                try {
                    $sellerRepository->create([[
                        "id" => $id,
                        "isApplied" => (bool) $isSellerRequested,
                        "isApproved" => (bool) $autoApproveSeller,
                        "profileStatus" => (bool) $storefrontHelper->getSystemConfigurationValue('WebkulMVMarketplace.config.profileAutoApprove', $salesChannelId),
                        "storeSlug" => $request->request->get('storeSlug'),
                        "type" => (int) $request->request->get('sellerType'),
                        "storeTitle" => $request->request->get('storeTitle'),
                        "storeOwner" => $payload['firstName'] . ' ' . $payload['lastName'],
                        "customer" => [
                            "id" => $payload['id']
                        ],
                    ]], $context);
                } catch (Exception $ex) {
                }
                // sending mail to admin and customer both
                try {

                    $mpMailHelper->applySellerShipMailToAdmin($payload['id'], $payload['salesChannelId']);
                } catch (Exception $ex) {
                }
                // assign auto membership to seller when membership add on is active
                $membershiPlugin = $this->container->get('plugin.repository')->search((new Criteria())->addFilter(new EqualsFilter('name', 'WebkulMPMembership'))->addFilter(new EqualsFilter('active', 1)), context::createDefaultContext())->first();
                if ($membershiPlugin) {
                    $membershipHelper = $this->container->get('membership.helper');
                    $membershipHelper->assignAutoMembershipToSeller($id);
                }
            }
        }

        return;
    }

    public function createMarketplaceOrder(EntityWrittenEvent $event)
    {

        if ($event->getEntityName() !== 'order') {
            return;
        }

        $context = $event->getContext();

        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getExistence() !== null && $writeResult->getExistence()->exists()) {
                break;
            }
            $payload = $writeResult->getPayload();
            if (isset($payload['salesChannelId'])) {

                $salesChannelId = $payload['salesChannelId'];
            }
            $orderRepository = $this->container->get('order.repository');
            $storefrontHelperService = $this->container->get('storefront.helper');
            $marketplaceOrderRepository = $this->container->get('marketplace_order.repository');
            $marketplaceProductRepository = $this->container->get('marketplace_product.repository');
            $marketplaceCommissionRepository = $this->container->get('marketplace_commission.repository');
            $sellerRepository = $this->container->get('marketplace_seller.repository');
            if (isset($payload['id'])) {

                $criteria = (new Criteria())
                    ->addFilter(new EqualsFilter('order.id', $payload['id']))
                    ->addAssociation('lineItems');


                // get order data
                $order = $orderRepository->search($criteria, $context)
                    ->first();
                // get order initial state id
                $stateId = $this->container->get('state_machine.repository')->search((new Criteria())->addFilter(new EqualsFilter('technicalName', 'order.state')), $context)->first();

                $lineItemsCollection = $order->get('lineItems')->getElements();

                foreach ($lineItemsCollection as $lineItem) {
                    $lineItemId = $lineItem->getId();
                    $productId = $lineItem->getIdentifier();
                    $quantity = $lineItem->getQuantity();
                    $productName = $lineItem->getLabel();
                    $totalPrice = $lineItem->getTotalPrice();
                    $lineItemTax = $lineItem->getPrice()->getCalculatedTaxes()->first()->getTax();
                    // get marketplace product by product id
                    $criteria = (new Criteria())
                        ->addFilter(new EqualsFilter('marketplace_product.productId.id', $productId))
                        ->addAssociation('product');

                    $marketplaceProduct = $marketplaceProductRepository->search($criteria, $context)
                        ->first();

                    // verify if product is of seller
                    if ($marketplaceProduct) {
                        $marketplaceProductId = $marketplaceProduct->getUniqueIdentifier();

                        // create marketplace order
                        $data = [
                            'id' => UUID::randomHex(),
                            'orderLineItemId' => $lineItemId,
                            'orderId' => $payload['id'],
                            'orderStatus' => $stateId->get('initialStateId'),
                            'currencyId' => $payload['currencyId'],
                            'marketplaceProductId' => $marketplaceProductId,
                        ];
                        $marketplaceOrderRepository->create([$data], $context);

                        // store commission details in database
                        // check membership plugin
                        $membershiPlugin = $this->container->get('plugin.repository')->search((new Criteria())->addFilter(new EqualsFilter('name', 'WebkulMPMembership'))->addFilter(new EqualsFilter('active', 1)), context::createDefaultContext())->first();
                        if ($storefrontHelperService->getSystemConfigurationValue('WebkulMVMarketplace.config.commissionOnTax', $salesChannelId) === true) {
                            $marketplaceProductPrice = $totalPrice;
                        } else {
                            $marketplaceProductPrice = $totalPrice - $lineItemTax;
                        }
                        $commissionAmount = null;
                        if ($membershiPlugin) {
                            $criteria = new Criteria();
                            $criteria->addFilter(new EqualsFilter('seller', $marketplaceProduct['marketplaceSellerId']))->addAssociation('marketplace_membership_group');
                            $memberRepository = $this->container->get('marketplace_membership_members.repository');
                            $membership = $memberRepository->search($criteria, $context)->first();
                            $groupCommission = $membership->get('marketplace_membership_group')->get('groupCommission');
                            if ($groupCommission) {
                                $categoryIds = $lineItem->getPayload()['categoryIds'];

                                foreach ($groupCommission as $commission) {

                                    if (in_array($commission['category'], $categoryIds)) {
                                        $commissionAmount = (($commission['percent'] * $marketplaceProductPrice) / 100) + $commission['fixed'];

                                        break;
                                    }
                                }
                            }
                        }

                        if (is_null($commissionAmount)) {

                            $sellerCriteria = (new Criteria())
                                ->addFilter(new EqualsFilter('marketplace_seller.id', $marketplaceProduct['marketplaceSellerId']));
                            // get admin particular commission
                            $adminCommission = $sellerRepository->search($sellerCriteria, $context)->first()->get('adminCommission');

                            if ($adminCommission == null) {
                                $adminCommission = $storefrontHelperService->getSystemConfigurationValue('WebkulMVMarketplace.config.commission', $salesChannelId);
                            }

                            $commissionAmount = (($adminCommission * $marketplaceProductPrice) / 100);
                        }
                        $commissionDetails = [
                            'isPaid' => false,
                            'marketplaceOrderId' => $data['id'],
                            'marketplaceSellerId' => $marketplaceProduct['marketplaceSellerId'],
                            'commissionAmount' => $commissionAmount,
                            'sellerEarning' => ($totalPrice - $commissionAmount),
                        ];

                        $marketplaceCommissionRepository->create([$commissionDetails], $context);
                        // check low stock and send notification to seller
                        $lowStockStatus = $storefrontHelperService->getSystemConfigurationValue('WebkulMVMarketplace.config.isLowStockNotify', $salesChannelId);

                        if ($lowStockStatus) {
                            $lowStockQty = $storefrontHelperService->getSystemConfigurationValue('WebkulMVMarketplace.config.lowStockCount', $salesChannelId);

                            $product = $this->container->get('product.repository')->search((new Criteria())->addFilter(new EqualsFilter('id', $productId)), $context)->first();
                            if ($product->getAvailableStock() < $lowStockQty) {
                                $customerId = $sellerRepository->search((new Criteria())->addFilter(new EqualsFilter('id', $marketplaceProduct['marketplaceSellerId'])), $context)->first()->get('customerId');

                                $storefrontHelperService->sendLowStockMail($productName, $customerId, $salesChannelId);
                            }
                        }
                    }
                }
            }
        }

        return;
    }
    public function orderPageLoadedEvent(AccountOrderPageLoadedEvent $event)
    {

        $customerId = $event->getSalesChannelContext()->getCustomer()->getId();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'cancelled'));
        $criteria->addAssociation('stateMachine');
        $criteria->addFilter(new EqualsFilter('stateMachine.technicalName', 'order.state'));
        $stateMachineRepository = $this->container->get('state_machine_state.repository');
        $cancelStateId = $stateMachineRepository->search($criteria, Context::createDefaultContext())->first()->getId();
        $mpOrderCriteria = new Criteria();
        $mpOrderCriteria->addAssociation('order');
        $mpOrderCriteria->addAssociation('order.customer');
        $mpOrderCriteria->addFilter(new EqualsFilter('orderStatus', $cancelStateId));
        $mpOrderCriteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $customerId));
        $marketplaceOrderRepository = $this->container->get('marketplace_order.repository');
        $mpOrderItem = $marketplaceOrderRepository->search($mpOrderCriteria, Context::createDefaultContext())->getElements();
        $cancelLineItemIds = [];
        $cancelOrderIds = [];

        foreach ($mpOrderItem as $order) {
            array_push($cancelLineItemIds, $order->get('orderLineItemId'));
            array_push($cancelOrderIds, $order->get('orderId'));
        }
        $cancelMpOrderId =  array_unique($cancelOrderIds);
        $marketplaceOrderRepository = $this->container->get('marketplace_order.repository');
        $marketplaceOrderLineItem = $marketplaceOrderRepository->search((new Criteria()), Context::createDefaultContext())->getElements();

        $shopwareOrder = $event->getPage()->getOrders()->getElements();
        foreach ($shopwareOrder as $order) {
            foreach ($order->getLineItems() as $lineItem) {
                if (in_array($order->getId(), $cancelMpOrderId)) {
                    if (in_array($lineItem->getId(), $cancelLineItemIds)) {
                        $order->setAmountTotal($order->getAmountTotal() - $lineItem->getTotalPrice());
                    }
                }
                foreach ($marketplaceOrderLineItem as $mpOrder) {
                    if ($lineItem->getId() === $mpOrder->get('orderLineItemId')) {
                        $lineItem->addExtension('itemStatus', new IdStruct($mpOrder->get('state_machine_state')->getName()));
                    }
                }
            }
        }
    }
    public function onloadcheckoutpage(CheckoutConfirmPageLoadedEvent $event)
    {
        // check DHL module is active or not
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'WebkulMPDhlShipping'))->addFilter(new EqualsFilter('active', 1));
        $pluginStatus = $this->container->get('plugin.repository')->search($criteria, Context::createDefaultContext());
        if ($pluginStatus->getTotal() == 0) {
            $shippingMethods = $this->container->get('shipping_method.repository')->search((new Criteria())->addFilter(new EqualsFilter('tags.name', 'MP-DHL'))->addAssociation('tags'), Context::createDefaultContext())->getElements();
            foreach ($shippingMethods as $shippingMethod) {
                $dhlShippingMethodIds[] = $shippingMethod->getId();
            }
            if ($dhlShippingMethodIds) {

                $criteria = new Criteria();

                $criteria->addFilter(new NotFilter(
                    NotFilter::CONNECTION_AND,
                    [
                        new EqualsAnyFilter('id', $dhlShippingMethodIds)
                    ]
                ))->addFilter(new EqualsFilter('active', 1));
                $shippingMethodsCollection = $this->container->get('shipping_method.repository')->search(
                    $criteria,
                    Context::createDefaultContext()
                );
                $event->getPage()->setShippingMethods($shippingMethodsCollection->getEntities());
            }
        }
    }
}
