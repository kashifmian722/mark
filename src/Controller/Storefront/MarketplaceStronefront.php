<?php declare(strict_types=1);

namespace Webkul\MultiVendor\Controller\Storefront;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\HttpFoundation\Request;
use Webkul\MultiVendor\Event\MarketplaceEvent;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Navigation\NavigationPageLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webkul\MultiVendor\EventDispatcher\MarketplaceEventDispatcher;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;

/**
 * @RouteScope(scopes={"storefront"})
 */
class MarketplaceStronefront extends StorefrontController
{
    /**
     * @var ContainerInterface
     */
    protected $container;
    /**
     * @var NavigationPageLoader
     */
    private $navigationPageLoader;

    public function __construct(ContainerInterface $container, NavigationPageLoader $navigationPageLoader)
    {
        $this->container = $container;
        $this->navigationPageLoader = $navigationPageLoader;
    }
    
    /**
     * @LoginRequired()
     * @Route("/marketplace/products", name="frontend.marketplace.products", methods={"GET"})
     */
    public function renderMarketplaceProducts(Request $request, SalesChannelContext $salesChannelContext)
    {
       
        $this->marketplaceDenyAccessUnlessSellerLoggedIn($salesChannelContext);

        $data = $this->navigationPageLoader->load($request, $salesChannelContext);

        return $this->renderStorefront('@WebkulMVMarketplace/storefront/products.html.twig', [
            'page' => $data,
            'productView' => 'list',
            'environment' => $this->container->getParameter('kernel.environment')
        ]);
    }
    /**
     * @Route("/marketplace/grouped/products", name="frontend.marketplace.grouped.products", methods={"GET"})
     */
    public function getMarketplaceGroupedProducts(Request $request, SalesChannelContext $salesChannelContext)
    {
        $params = $request->query->all();
        $customerId = $salesChannelContext->getCustomer()->getId();
        $context = Context::createDefaultContext();
        $marketplaceProductRepository = $this->container->get('marketplace_product.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('marketplace_product.marketplace_seller.customer.id', $customerId))
            ->addAssociation('product')
            ->addFilter(new EqualsFilter('product.active',1))
            ->addAssociation('product.options')
            ->addSorting(new FieldSorting('createdAt', 'DESC'));
          
        if (isset($params['pageGroup']) && isset($params['limit'])) {
            $criteria = $criteria->setLimit((int)$params['limit'])
                ->setOffset(((int)$params['pageGroup'] - 1) * (int)$params['limit']);
        }
         // get object of product collection
         $productsCollection = $marketplaceProductRepository->search($criteria, $context)
         ->getElements();
         
         $propertyGroupRepository = $this->container->get('property_group.repository');
         $list = [];
         $parentIdCollection = [];
        if($productsCollection) {
            foreach($productsCollection as $product){
                
                $propertyData = [];
                if ($product['product']->get('options')->getElements()) {
                    $options = $product['product']->get('options')->getElements();
                    
                    foreach($options as $option) {
                        
                        $groupId = $option->getGroupId();
                        $groupName = $option->getName();
                        $groupData  = $propertyGroupRepository->search(
                            (new Criteria())
                            ->addFilter(new EqualsFilter('id', $groupId)),
                            Context::createDefaultContext()
                        )->getElements();
                        
                        foreach($groupData as $group) {
                            $propertyData[]  = [
                                $group->getName() => $groupName
                            ];
                        }
                    }
                }
                if ($product['product']->get('parentId')) {
                   
                    $parentIdCollection[] = $product['product']->get('parentId');
                    $parentId = $product['product']->get('parentId');
                    $productRepository = $this->container->get('product.repository');
                    $productSw = $productRepository->search(
                        (new Criteria())
                        ->addFilter(new EqualsFilter('id', $parentId)),
                        Context::createDefaultContext()
                    )->first();
                    
                    $product['product']->setName($productSw->get('name'));
                    if(!$product['product']->get('price')){
                        $product['product']->setPrice($productSw->get('price'));
                    }
                    
                }
                
                $list[] = [
                    'id'=>$product['product']->get('id'),
                    'name'=>$product['product']->get('name'),
                    'productNumber'=>$product['product']->get('productNumber'),
                    'price' =>$product['product']->get('price')->first()->getGross(),
                    'propertyData'=>$propertyData,
                    'stock'=> $product['product']->get('availableStock')
                ];
                $propertyData = [];

            }
        }
        
        $parentIds = array_unique($parentIdCollection);
        
        foreach($list as $key=>$value) {
            if (in_array($value['id'],$parentIds)) {
                unset($list[$key]);
            }
        }
        
        $countCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('marketplace_product.marketplace_seller.customer.id', $customerId))->addAssociation('product')->addFilter(new EqualsFilter('product.active',1));
        $products = $marketplaceProductRepository->search($countCriteria,$context)->getElements();
        
        $productParentIds = [];
        foreach($products as $key=>$product){
            
            if($product->get('product')->getParentId()) {
                $productParentIds[] = $product->get('product')->getParentId();
            }
        }
        $productParentIds = array_unique($productParentIds);
        foreach($products as $index=>$product){
            if(in_array($product->get('product')->getId(),$productParentIds)){
                
                unset($products[$index]);
            }
        }
        
        $total = sizeof($products);
        
        return new JsonResponse(['list'=>$list,'total'=>$total]);
        
    }

    /**
     * @LoginRequired()
     * @Route("/marketplace/product/add", name="frontend.marketplace.product.add", methods={"GET"})
     */
    public function renderAddMarketplaceProduct(Request $request, SalesChannelContext $salesChannelContext)
    {
        $this->marketplaceDenyAccessUnlessSellerLoggedIn($salesChannelContext);

        $data = $this->navigationPageLoader->load($request, $salesChannelContext);

        return $this->renderStorefront('@WebkulMVMarketplace/storefront/products.html.twig', [
            'page' => $data,
            'productView' => 'add',
            'environment' => $this->container->getParameter('kernel.environment')
        ]);
    }

    /**
     * @LoginRequired()
     * @Route("/marketplace/orders", name="frontend.marketplace.orders", methods={"GET"})
     */
    public function renderMarketplaceOrders(Request $request, SalesChannelContext $salesChannelContext)
    {
        $this->marketplaceDenyAccessUnlessSellerLoggedIn($salesChannelContext);

        $data = $this->navigationPageLoader->load($request, $salesChannelContext);

        return $this->renderStorefront('@WebkulMVMarketplace/storefront/orders.html.twig', [
            'page' => $data,
            'environment' => $this->container->getParameter('kernel.environment')
        ]);
    }

    /**
     * @LoginRequired()
     * @Route(
     *      "/marketplace/seller",
     *      name="frontend.marketplace.seller.profile",
     *      methods={"GET"},
     * )
     */
    public function renderMarketplaceSellerProfile(Request $request, SalesChannelContext $salesChannelContext)
    {
        $this->marketplaceDenyAccessUnlessSellerLoggedIn($salesChannelContext);

        $data = $this->navigationPageLoader->load($request, $salesChannelContext);

        return $this->renderStorefront('@WebkulMVMarketplace/storefront/profile.html.twig', [
            'page' => $data,
            'environment' => $this->container->getParameter('kernel.environment')
        ]);
    }

    /**
     * @Route(
     *      "/marketplace/sellers",
     *      name="frontend.marketplace.sellers",
     *      methods={"GET"},
     * )
     */
    public function renderMarketplaceSellers(Request $request, SalesChannelContext $salesChannelContext)
    {
        $page = $request->query->get('p');
        $limit = (int)12;
        // get seller data
        $criteria = (new Criteria())->addFilter(new EqualsFilter('isApproved',1))->addAssociations(['mediaLogo','mediaOwner'])->setLimit($limit);
        if(isset($page) && $page!= '') {
            $criteria = $criteria->setLimit($limit)
                ->setOffset(($page - 1) * $limit);
        }
        // check hyperlocal plugin
        $hyperlocalPluginSatus = $this->container->get('plugin.repository')->search((new Criteria())->addFilter(new EqualsFilter('name','WebkulMPHyperlocal'))->addFilter(new EqualsFilter('active',1)), $salesChannelContext->getContext())->first();
        if($hyperlocalPluginSatus) {
            $session = new Session();
            $criteria->addFilter(new EqualsAnyFilter('id',$session->get('sellerIds')));
        }
        $sellers = $this->container->get('marketplace_seller.repository')->search($criteria,Context::createDefaultContext())->getElements();
        foreach ($sellers as $key=>$seller) {
            $sellerProducts = $this->container->get('marketplace_product.repository')->search((new Criteria())->addFilter(new EqualsFilter('marketplaceSellerId',$seller->get('id'))),Context::createDefaultContext());
            $sellers[$key]['noOfProducts'] =  $sellerProducts->getTotal();
        }
        $totalSellers = $this->container->get('marketplace_seller.repository')->search((new Criteria())->addFilter(new EqualsFilter('isApproved',1)),$salesChannelContext->getContext())->getTotal();
        
        // get config data
        $configData = $this->getConfigData($salesChannelContext);
        $data = $this->navigationPageLoader->load($request, $salesChannelContext);

        return $this->renderStorefront('@WebkulMVMarketplace/storefront/mp-seller-list.html.twig', [
            'page' => $data,
            'limit' => $limit,
            'marketplaceSellers' => $sellers,
            'configData' => $configData,
            'totalSellers' => $totalSellers,
            'environment' => $this->container->getParameter('kernel.environment')
        ]);
    }

    /**
     * @Route(
     *      "/marketplace/seller/{customerId}",
     *      name="frontend.marketplace.seller.public.profile",
     *      methods={"GET"},
     * )
     */
    public function renderMarketplaceSellerPublicProfile(Request $request, $customerId, SalesChannelContext $salesChannelContext)
    {
        // add subscriber to event
        
        $dispatcher = new MarketplaceEventDispatcher('marketplace.profile', $this->container);
        $marketplaceEvent = new MarketplaceEvent('marketplace.profile');
        $marketplaceEvent->setEventData([
            'customerId' => $customerId,
            'params' => $request->query->all(),
            "salesChannelContext" => $salesChannelContext
        ]);
        //dispatch action
        $dispatcher->dispatch($marketplaceEvent, MarketplaceEvent::MARKETPLACE_STORE_GET_PROFILE);
        $eventResponse = $marketplaceEvent->getEventResponse();
        // if seller profile status is not active
       
        if ($eventResponse['status'] == false) {
            throw new NotFoundHttpException("Page not found");
        }
        $data = $this->navigationPageLoader->load($request, $salesChannelContext);
        
        return $this->renderStorefront('@WebkulMVMarketplace/storefront/public-profile.html.twig', [
            'page' => $data,
            'profileDetails' => $eventResponse,
            "currency" => $salesChannelContext->getCurrency(),
            'environment' => $this->container->getParameter('kernel.environment')
        ]);
    }
       /**
        * @LoginRequired()
     * @Route("/marketplace/dashboard", name="frontend.marketplace.dashboard", methods={"GET"})
     */
    public function renderSellerDashboard(Request $request, SalesChannelContext $salesChannelContext) 
    {
        $this->marketplaceDenyAccessUnlessSellerLoggedIn($salesChannelContext);

        $data = $this->navigationPageLoader->load($request, $salesChannelContext);

        return $this->renderStorefront('@WebkulMVMarketplace/storefront/dashboard.html.twig', [
            'page' => $data,
            'environment' => $this->container->getParameter('kernel.environment')
        ]);
    }
    /**
     * @Route("/marketplace/storefront/product/status", name="frontend.marketplace.products.status", methods={"POST"}, defaults={"csrf_protected"=false, "XmlHttpRequest"=true})
     */
    public function updateProductStatus(Request $request, SalesChannelContext $salesChannelContext)
    {
        $productId = $request->request->get('params')['id'];
        
        $active = $request->request->get('params')['active'];
        $productRepository = $this->container->get('product.repository');
        $productRepository->update([['id'=>$productId, 'active' => $active]], $salesChannelContext->getContext());
        return new JsonResponse(['status'=>true, 'message'=> $this->trans('wk-mp.product.list.productStatusChangeMessage')]);
    }
   

    private function marketplaceDenyAccessUnlessSellerLoggedIn(SalesChannelContext $salesChannelContext)
    {
        

        $marketplaceSellerRepository = $this->container->get('marketplace_seller.repository');
        
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('customerId', $salesChannelContext->getCustomer()->getId()));

        $seller = $marketplaceSellerRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->getElements();
        
        if(empty($seller)) {
            throw new NotFoundHttpException("Page not found");
        }
        if (!$seller[array_keys($seller)[0]]->get('isApproved')) {
            throw new NotFoundHttpException("Page not found");
        }
    }
    /**
     * @Route("marketplace/storefront/sell", name="frontend.marketplace.storefront.sell")
     */
    public function renderMarketplaceSellPage(Request $request, SalesChannelContext $salesChannelContext)
    {
        $data = $this->navigationPageLoader->load($request, $salesChannelContext);
        // get config data
        $configData = $this->getConfigData($salesChannelContext);
        // get seller data
        $sellers = $this->container->get('marketplace_seller.repository')->search((new Criteria())->addFilter(new EqualsFilter('isApproved',1))->addFilter(new EqualsFilter('profileStatus',1))->setLimit((int)4)->addAssociations(['mediaLogo','mediaOwner']),Context::createDefaultContext())->getElements();
        foreach ($sellers as $key=>$seller) {
            $sellerProducts = $this->container->get('marketplace_product.repository')->search((new Criteria())->addFilter(new EqualsFilter('marketplaceSellerId',$seller->get('id'))),Context::createDefaultContext());
            $sellers[$key]['noOfProducts'] =  $sellerProducts->getTotal();
        }
       
        return $this->renderStorefront('@WebkulMVMarketplace/storefront/sell.html.twig', [
            'page' => $data,
            'configData' => $configData,
            'sellers' => $sellers
        ]);
    }
    private function getConfigData($salesChannelContext) {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $configData = [];
        $storefrontHelper = $this->container->get('storefront.helper');
        
        $bannerImageId = $storefrontHelper->getSystemConfigurationValue('WebkulMVMarketplace.config.bannerImageId',$salesChannelId);
        $feature1Icon = $storefrontHelper->getSystemConfigurationValue('WebkulMVMarketplace.config.feature1Icon',$salesChannelId);
        $feature2Icon = $storefrontHelper->getSystemConfigurationValue('WebkulMVMarketplace.config.feature2Icon',$salesChannelId);
        $feature3Icon = $storefrontHelper->getSystemConfigurationValue('WebkulMVMarketplace.config.feature3Icon',$salesChannelId);
        $feature4Icon = $storefrontHelper->getSystemConfigurationValue('WebkulMVMarketplace.config.feature4Icon',$salesChannelId);
        
        if (isset($bannerImageId))
            $configData['bannerImage'] = $this->getMedia($bannerImageId);
        if (isset($feature1Icon))
            $configData['feature1Media'] = $this->getMedia($feature1Icon);
        if (isset($feature2Icon))
            $configData['feature2Media'] = $this->getMedia($feature2Icon);
        if (isset($feature3Icon))
            $configData['feature3Media'] = $this->getMedia($feature3Icon);
        if (isset($feature4Icon))
            $configData['feature4Media'] = $this->getMedia($feature4Icon);
        return $configData;
    }
    private function getMedia($id) {
        return $this->container->get('media.repository')->search((new Criteria())->addFilter(new EqualsFilter('id',$id)),Context::createDefaultContext())->first();
    }
    /**
     * @Route("wk/marketplace/contact/seller", name="frontend.marketplace.contact.seller", methods={"POST"}, defaults={"csrf_protected"=false, "XmlHttpRequest"=true})
     */
    public function ContactSeller(Request $request, SalesChannelContext $salesChannelContext)
    {
        $mailHelper = $this->container->get('mpmail.helper');
        $mailHelper->customerToSeller($request->request->all(),$salesChannelContext->getSalesChannelId());
        return new JsonResponse(true);
    }
    /**
     * @LoginRequired()
     * @Route("wk/marketplace/earnings", name="frontend.marketplace.earnings")
     */
    public function renderSellerEarningPage(Request $request, SalesChannelContext  $salesChannelContext)
    {
        $query = $request->query->all();
        if ($query) {
        $context = Context::createDefaultContext();
        $customerId = $salesChannelContext->getCustomer()->getId();
        $marketplaceOrderRepository = $this->container->get('marketplace_commission.repository');
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('marketplace_commission.marketplace_order.marketplace_product.marketplace_seller.customerId', $customerId))
            ->addFilter(new RangeFilter('createdAt', ['gte'=> $query['from'],'lte'=>$query['to']]));
        $graphOrder = $marketplaceOrderRepository->search($criteria, $context);
        if ($graphOrder) {

            foreach($graphOrder as $order) {
                $graphOrderDate[] = $order->get('createdAt')->format(Defaults::STORAGE_DATE_FORMAT);
            }
            $dayWise = array_count_values($graphOrderDate);
            // get monthly data
            foreach($dayWise as $key=>$val){
                $monthly[substr($key,0,7)][] = $val;
            }
            foreach($monthly as $month=>$value){
                $monthWise[$month] = array_sum($value);
            }
            // get yearly data
            foreach($dayWise as $key=>$val){
                $yearly[substr($key,0,4)][] = $val;
            }
            foreach($yearly as $year=>$value){
                $yearWise[$year] = array_sum($value);
            }
        }
        }
        
        $this->marketplaceDenyAccessUnlessSellerLoggedIn($salesChannelContext);

        $data = $this->navigationPageLoader->load($request, $salesChannelContext);

        return $this->renderStorefront('@WebkulMVMarketplace/storefront/seller-earnings.html.twig', [
            'page' => $data,
            'environment' => $this->container->getParameter('kernel.environment')
        ]);
    }
    /**
     * @LoginRequired()
     * @Route("wk/marketplace/earnings/filter", name="frontend.marketplace.earnings.filter")
     */
    public function filterEarningData(Request $request, SalesChannelContext  $salesChannelContext)
    {
        $query = $request->query->all();
        
        $customerId = $salesChannelContext->getCustomer()->getId();
        $currency = $salesChannelContext->getCurrency();
        $currencyFactor = $currency->getFactor();
        $context = Context::createDefaultContext();
        $marketplaceOrderRepository = $this->container->get('marketplace_commission.repository');
        $criteria = (new Criteria())->addFilter(new EqualsFilter('marketplace_commission.marketplace_order.marketplace_product.marketplace_seller.customerId', $customerId))
        ->addAssociation('marketplace_order')
        ->addAssociation('marketplace_order.order');
        if($query['from'] != '') {
              
            if($query['to'] == ''){
                $query['to'] = date('Y-m-d');
            } else{
                $query['to'] = date('Y-m-d', strtotime('+1 day', strtotime($query['to'])));
            }
            
            $criteria->addFilter(new RangeFilter('createdAt', ['gte'=> $query['from'],'lte'=>$query['to']]));
        }
        $sales = $marketplaceOrderRepository->search($criteria, $context)->getElements();
        
        $salesRecord = array();
        $earningData = array();
        $salesData = array();
        if ($sales) {

            foreach($sales as $sale)
            {
                
                $salesRecord[$sale->get('createdAt')->format(DEFAULTS::STORAGE_DATE_FORMAT)][] = $sale->get('sellerEarning');
            }
            foreach($sales as $sale)
            {
                
                $earningData[$sale->get('createdAt')->format(DEFAULTS::STORAGE_DATE_FORMAT)][] = ['sellerEarning'=>$sale->get('sellerEarning'),'commissionAmount'=>$sale->get('commissionAmount'),'totalAmount'=>$sale->get('sellerEarning')+$sale->get('commissionAmount')];
            }
        }
        if (isset($earningData)) {
            foreach($earningData as $key=>$data) {
                $commission = 0;$sellerEarning= 0;$totalAmount= 0;$orderCount= 0;
                foreach($data as $val) {
                    $commission += (float)$val['commissionAmount'];
                    $orderCount++;
                    $sellerEarning += (float)$val['sellerEarning'];
                    $totalAmount += (float)$val['totalAmount'];
                    $salesData[$key] = ['commission'=>$commission,'earning'=>$sellerEarning,'totalAmount'=>$totalAmount,'orderCount'=>$orderCount] ;
                }
                
            }
            
            $salesAmount=[];
            foreach($salesRecord as $date=>$value) {
                    
                    $salesAmount[$date] = number_format(array_sum($value),2); 
            
            }
            foreach($salesAmount as $date=>$amount) {
                $salesAmount[$date] = $currencyFactor*$amount;
            }
            $salesReport = [];
            if ($query['period'] == 'day' || $query['period'] == '') {
                $salesReport = $salesAmount;
                $salesReportList = $salesData;
            }
            if ($query['period'] == 'month') {
                $monthlyList = [];
                // get monthly data
                foreach($salesAmount as $key=>$val){
                    $monthly[substr($key,0,7)][] = $val;
                }
                
                foreach($monthly as $month=>$value){
                    $salesReport[$month] = number_format(array_sum($value),2);
                }
                
                foreach($salesData as $key=>$val) {
                    $monthlyList[substr($key,0,7)][] = $val;
                }
                foreach($monthlyList as $key=>$list) {
                    $commission = 0; $earningAmount = 0; $totalAmount = 0; $orderCount = 0;
                    foreach ($list as $value) {
                        $commission+= $value['commission'];
                        $earningAmount+= $value['earning'];
                        $totalAmount+= $value['totalAmount'];
                        $orderCount+= $value['orderCount'];
                        $salesReportList[$key] = ['commission'=>$commission,'earning'=>$earningAmount,'totalAmount'=>$totalAmount,'orderCount'=>$orderCount];
                    }
                }
                
                
            }
            if ($query['period'] == 'year') {

                // get yearly data
                foreach($salesAmount as $key=>$val){
                    $yearly[substr($key,0,4)][] = $val;
                }
                foreach($yearly as $year=>$value){
                    $salesReport[$year] = number_format(array_sum($value),2);
                }
                foreach($salesData as $key=>$val) {
                    $yearlyList[substr($key,0,4)][] = $val;
                }
                foreach($yearlyList as $key=>$list) {
                    $commission = 0; $earningAmount = 0; $totalAmount = 0; $orderCount = 0;
                    foreach ($list as $value) {
                        $commission+= $value['commission'];
                        $earningAmount+= $value['earning'];
                        $totalAmount+= $value['totalAmount'];
                        $orderCount+= $value['orderCount'];
                        $salesReportList[$key] = ['commission'=>$commission,'earning'=>$earningAmount,'totalAmount'=>$totalAmount,'orderCount'=>$orderCount];
                    }
                }
            }
        }
        
        return new JsonResponse(['currency'=>$currency,'salesGraphData'=>$salesReport,'salesListData'=>$salesReportList]);
    }
}
