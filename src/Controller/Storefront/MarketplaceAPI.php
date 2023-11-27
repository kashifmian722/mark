<?php declare(strict_types=1);

namespace Webkul\MultiVendor\Controller\Storefront;

use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Webkul\MultiVendor\Event\MarketplaceEvent;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Webkul\MultiVendor\EventDispatcher\MarketplaceEventDispatcher;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @RouteScope(scopes={"storefront"})
 */
class MarketplaceAPI extends StorefrontController
{
    const OK_RESPONSE_CODE = 200;
    const CREATED_RESPONSE_CODE = 202;
    const CONFLICT_RESPONSE_CODE = 409;

    const ORDER_EVENT_SECTION = 'marketplace.order';
    const PRODUCT_EVENT_SECTION = 'marketplace.product';
    const PROFILE_EVENT_SECTION = 'marketplace.profile';
    const REPORT_EVENT_SECTION = 'marketplace.report';

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @Route(
     *      "/storefront-api/{customerId}/products/{productId}",
     *      name="frontend.marketplace.api.products",
     *      defaults={"productId": null, "csrf_protected"=false, "XmlHttpRequest"=true},
     *      methods={"GET", "POST", "DELETE", "PATCH"}
     * )
    */
    public function marketplaceProductsAPI(
        $customerId,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): JsonResponse {
        $requestParams = $request->request->all();

        // add subscriber to event
        $dispatcher = new MarketplaceEventDispatcher(self::PRODUCT_EVENT_SECTION, $this->container);
        $marketplaceEvent = new MarketplaceEvent(self::PRODUCT_EVENT_SECTION);

        $marketplaceEvent->setEventData([
            'customerId' => $customerId,
        ]);

        switch ($request->getMethod()) {
            case 'GET':
                $eventName = MarketplaceEvent::MARKETPLACE_PRODUCT_LIST;

                $marketplaceEvent->setEventData([
                    'params' => $request->query->all(),
                    "salesChannelContext" => $salesChannelContext
                ]);

                break;

            case 'POST':
                if ($requestParams['requestMethod'] == "POST") {
                    $eventName = MarketplaceEvent::MARKETPLACE_PRODUCT_CREATE;
                } elseif ($requestParams['requestMethod'] == "PATCH") {
                    $eventName = MarketplaceEvent::MARKETPLACE_PRODUCT_UPDATE;
                }

                $marketplaceEvent->setEventData([
                    'productDetails' => $requestParams,
                    'productMedia' => $request->files->get('images'),
                    "salesChannelContext" => $salesChannelContext
                ]);

                break;

            case 'DELETE':
                $eventName = MarketplaceEvent::MARKETPLACE_PRODUCT_DELETE;
                $marketplaceEvent->setEventData([
                    'params' => $request->query->all(),
                    "salesChannelContext" => $salesChannelContext
                ]);

                break;

            default:
                break;
        }

        // dispatch action
        $dispatcher->dispatch($marketplaceEvent, $eventName);
        $eventResponse = $marketplaceEvent->getEventResponse();
        
        return new JsonResponse($eventResponse, self::OK_RESPONSE_CODE);
    }

    /**
     * @Route(
     *      "/storefront-api/variant/{productId}",
     *      name="frontend.marketplace.api.variants",
     *      defaults={"productId": null,"csrf_protected"=false, "XmlHttpRequest"=true},
     *      methods={"GET", "POST", "DELETE"}
     * )
    */
    public function marketplaceProductVariantAPI(Request $request, SalesChannelContext $salesChannelContext, $productId): JsonResponse
    {
        $requestParams = $request->request->all();

        // add subscriber to event
        $dispatcher = new MarketplaceEventDispatcher(self::PRODUCT_EVENT_SECTION, $this->container);
        $marketplaceEvent = new MarketplaceEvent(self::PRODUCT_EVENT_SECTION);

        switch ($request->getMethod()) {
            case 'GET':
                break;

            case 'POST':
                if ($requestParams['requestMethod'] == 'VARIANT') {
                    $eventName = MarketplaceEvent::MARKETPLACE_PRODUCT_VARIANT_UPDATE;
                } else if ($requestParams['requestMethod'] == 'VARIANT_LIST') {
                    $requestParams = $requestParams['variants'];
                    $eventName = MarketplaceEvent::MARKETPLACE_PRODUCT_VARIANT_LIST_UPDATE;

                    $marketplaceEvent->setEventData([
                        'productId' => $productId,
                        'customerId' => $salesChannelContext->getCustomer()->getId(),
                    ]);
                }

                $marketplaceEvent->setEventData([
                    'variantDetails' => $requestParams,
                ]);

                break;

            case 'DELETE':
                $marketplaceEvent->setEventData([
                    'productId' => $productId,
                    'variantId' => $request->query->get('variantId'),
                ]);

                $eventName = MarketplaceEvent::MARKETPLACE_PRODUCT_VARIANT_DELETE;

                break;

            default:
                break;
        }

        // dispatch action
        $dispatcher->dispatch($marketplaceEvent, $eventName);
        $eventResponse = $marketplaceEvent->getEventResponse();

        return new JsonResponse($eventResponse, self::OK_RESPONSE_CODE);
    }

    /**
     * @Route(
     *      "/storefront-api/{customerId}/orders",
     *      name="frontend.marketplace.api.orders",
     *      methods={"GET", "POST", "PATCH", "DELETE"}
     * )
    */
    public function marketplaceOrderAPI(
        Request $request,
        $customerId,
        SalesChannelContext $salesChannelContext
    ): JsonResponse {

        // add subscriber to event
        $dispatcher = new MarketplaceEventDispatcher(self::ORDER_EVENT_SECTION, $this->container);
        $marketplaceEvent = new MarketplaceEvent(self::ORDER_EVENT_SECTION);
        $requestParams = $request->request->all();
        switch($request->getMethod()) {
            case 'PATCH' :
                $eventName = MarketplaceEvent::MARKETPLACE_EVENT_ORDER_UPDATE;

                $marketplaceEvent->setEventData([
                    'params' => $request->request->all(),
                    "salesChannelContext" => $salesChannelContext
                ]);
                break;
            case 'GET':
                $eventName = MarketplaceEvent::MARKETPLACE_EVENT_ORDER_LIST;
                if ($queryParams = $request->query->all()) {
                    $marketplaceEvent->setEventData([
                        'params' => $queryParams,
                        'customerId' => $customerId,
                        'salesChannel' => $salesChannelContext
                    ]);
                }
                break;
            default:
                break;   

        }
     

        // dispatch action
        $dispatcher->dispatch($marketplaceEvent, $eventName);
        $eventResponse = $marketplaceEvent->getEventResponse();

        $eventResponse += [
            'status' => true,
            'currencySymbol' => $salesChannelContext->getCurrency()->getSymbol(),
            'paymentMethod' => $salesChannelContext->getPaymentMethod()->getName(),
            'shippingMethod' => $salesChannelContext->getShippingMethod()->getName(),
        ];

        return new JsonResponse($eventResponse, self::OK_RESPONSE_CODE);
    }

    /**
     * @Route(
     *      "/storefront-api/{customerId}/profile",
     *      name="frontend.marketplace.api.profile",
     *      methods={"GET", "POST"},
     *      defaults={"csrf_protected"=false, "XmlHttpRequest"=true}
     * )
    */
    public function marketplaceStoreProfileAPI(Request $request, $customerId, SalesChannelContext $salesChannelContext): JsonResponse
    {
        // add subscriber to event
        $dispatcher = new MarketplaceEventDispatcher(self::PROFILE_EVENT_SECTION, $this->container);
        $marketplaceEvent = new MarketplaceEvent(self::PROFILE_EVENT_SECTION);
        
        $marketplaceEvent->setEventData([
            'customerId' => $customerId,
            'params' => $request->query->all(),
            "salesChannelContext" => $salesChannelContext
        ]);
                    
        if ($request->getMethod() == "GET") {
            $event = MarketplaceEvent::MARKETPLACE_STORE_GET_PROFILE;
        } else{

            $marketplaceEvent->setEventData([
                'storeDetails' => $request->request->all(),
                'attachments' => $request->files->all(),
                "salesChannelContext" => $salesChannelContext
            ]);

            $event = MarketplaceEvent::MARKETPLACE_SELLER_UPDATE_PROFILE;
        }
        

        // dispatch action
        $dispatcher->dispatch($marketplaceEvent, $event);
        $eventResponse = $marketplaceEvent->getEventResponse();

        return new JsonResponse($eventResponse, self::OK_RESPONSE_CODE);
    }

    /**
     * @Route(
     *      "/storefront-api/profile/{productId}",
     *      name="frontend.marketplace.api.profile.product",
     *      methods={"GET", "POST"}
     * )
    */
    public function mpStoreProfileByProductId(Request $request,SalesChannelContext $salesChannelContext, $productId): JsonResponse
    {
        $mpProductRepository = $this->container->get('marketplace_product.repository');

        $criteria = (new Criteria())
            ->addAssociation('marketplace_seller')
            ->addFilter(new EqualsFilter('productId', $productId));
        $context = Context::createDefaultContext();

        // get customer id
        $mpProduct = $mpProductRepository->search($criteria, $context);
        
        if ($mpProduct->getTotal()) {
            $customerId = $mpProduct->first()->get('marketplace_seller')->get('customerId');

            // add subscriber to event
            $dispatcher = new MarketplaceEventDispatcher(self::PROFILE_EVENT_SECTION, $this->container);
            $marketplaceEvent = new MarketplaceEvent(self::PROFILE_EVENT_SECTION);
            $marketplaceEvent->setEventData([
                'customerId' => $customerId,
                'salesChannelContext' => $salesChannelContext
            ]);
            

            $event = MarketplaceEvent::MARKETPLACE_STORE_GET_PROFILE;

            // dispatch action
            $dispatcher->dispatch($marketplaceEvent, $event);
            $eventResponse = $marketplaceEvent->getEventResponse();
        } else {
            $eventResponse = [
                'status' => false
            ];
        }

        $eventResponse['environment'] = $this->container->getParameter('kernel.environment');

        return new JsonResponse($eventResponse, self::OK_RESPONSE_CODE);
    }

    /**
     * @Route(
     *      "/storefront-api/{reviewerId}/profile/review/{storeOwnerId}",
     *      name="frontend.marketplace.api.profile.review",
     *      methods={"GET", "POST"},
     *      defaults={"csrf_protected"=false, "XmlHttpRequest"=true}
     * )
    */
    public function marketplaceStoreReview(Request $request, $reviewerId, $storeOwnerId): JsonResponse
    {
        // add subscriber to event
        $dispatcher = new MarketplaceEventDispatcher(self::PROFILE_EVENT_SECTION, $this->container);

        $marketplaceEvent = new MarketplaceEvent(self::PROFILE_EVENT_SECTION);

        $marketplaceEvent->setEventData([
            'reviewerId' => $reviewerId,
            'storeOwnerId' => $storeOwnerId,
            'review' => $request->request->all(),
        ]);

        $event = MarketplaceEvent::MARKETPLACE_SELLER_ADD_REVIEW;

        // dispatch action
        $dispatcher->dispatch($marketplaceEvent, $event);
        $eventResponse = $marketplaceEvent->getEventResponse();

        return new JsonResponse($eventResponse, self::OK_RESPONSE_CODE);
    }
     /**
     * @Route("/storefront-api/{customerId}/marketplace/report", name="frontend.marketplace.api.sales.report"),           methods={"GET"})
     */
    public function marketplaceSalesReport(Request $request, $customerId, SalesChannelContext $salesChannelContext): JsonResponse
    {
        // add subscriber to event
        $dispatcher = new MarketplaceEventDispatcher(self::REPORT_EVENT_SECTION, $this->container);
        $marketplaceEvent = new MarketplaceEvent(self::REPORT_EVENT_SECTION);

        $marketplaceEvent->setEventData([
            'customerId' => $customerId,
            'salesChannelContext' => $salesChannelContext
        ]);

        $eventName = MarketplaceEvent::MARKETPLACE_REPORT_ORDER;

        $marketplaceEvent->setEventData([
            'params' => $request->query->all()
        ]);
        // dispatch action
        $dispatcher->dispatch($marketplaceEvent, $eventName);
        $eventResponse = $marketplaceEvent->getEventResponse();
        $eventResponse += [
            'status' => true,
            'currency' => $salesChannelContext->getCurrency(),
        ];    
        return new JsonResponse($eventResponse, self::OK_RESPONSE_CODE);
    }
    /**
     * @Route("/storefront-api/{customerId}/seller/transaction", name="frontend.marketplace.seller.transaction")
     */
    public function marketplaceSellerTransaction(Request $request, $customerId, SalesChannelContext $salesChannelContext)
    {
        $page = (int)$request->query->get('page');
        $limit = (int)$request->query->get('limit');
        
        $context = Context::createDefaultContext();
        $markeplaceCommissionRepo = $this->container->get('marketplace_commission.repository');
        $criteria = (new Criteria())->addFilter(new EqualsFilter('marketplace_commission.marketplace_seller.customerId', $customerId))
                ->addSorting(new FieldSorting('updatedAt', FieldSorting::DESCENDING))
                ->addAssociation('marketplace_seller')
                ->addAssociation('marketplace_order.order');
        if ($page && $limit) {
            $criteria = $criteria->setLimit($limit)
                ->setOffset(($page - 1) * $limit);
        }
        $transactions = $markeplaceCommissionRepo->search($criteria, $context)->getElements();
        $totalSellerIncome = 0;
        $paidTransaction = 0;
        $totalAdminCommission = 0;
        $totalTransaction  = 0;
        
        $totalCriteria = (new Criteria())->addFilter(new EqualsFilter('marketplace_commission.marketplace_seller.customerId', $customerId))
        ->addAssociation('marketplace_seller')
        ->addAssociation('marketplace_order.order');
        $totalTransactions = $markeplaceCommissionRepo->search($totalCriteria, $context);
       
        foreach($totalTransactions as $transaction) {
            $totalSellerIncome += $transaction->get('sellerEarning');
            $totalAdminCommission += $transaction->get('commissionAmount');
            $totalTransaction += $transaction->get('marketplace_order')->get('order')->get('positionPrice');
            if($transaction->get('isPaid')) {
                $paidTransaction += $transaction->get('sellerEarning');
            }
        }
        return new JsonResponse(['transactions'=>$transactions,'sellerIncome'=>$totalSellerIncome, 'paidTransaction'=>$paidTransaction,'adminCommission'=>$totalAdminCommission, 'totalTransaction'=>$totalTransaction, 'currency' => $salesChannelContext->getCurrency(),'total'=> $totalTransactions->getTotal()]);
    }
    
}
