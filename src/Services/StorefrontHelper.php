<?php

namespace Webkul\MultiVendor\Services;

use Exception;
use Shopware\Core\Content\Mail\Service\MailService as ServiceMailService;
use Webkul\MultiVendor\Services\GlobalService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

class StorefrontHelper extends GlobalService
{
    private $container;
    private $mediaUpdater;
    private $fileNameProvider;
    private $systemConfigService;
    private $mailService;

    public function __construct(
        ContainerInterface $container,
        FileSaver $mediaUpdater,
        FileNameProvider $fileNameProvider,
        SystemConfigService $systemConfigService,
        ServiceMailService $mailService
    ) {
        $this->container = $container;
        $this->mediaUpdater = $mediaUpdater;
        $this->fileNameProvider = $fileNameProvider;
        $this->systemConfigService = $systemConfigService;
        $this->mailService = $mailService;
    }

    public function getSellerId($customerId)
    {
        $sellerRepository = $this->container->get('marketplace_seller.repository');

        // get customer from marketplace_seller entity
        try {
            $marketplaceSeller = $sellerRepository->search(
                (new Criteria())->addFilter(new EqualsFilter('marketplace_seller.customer.id', $customerId)),
                Context::createDefaultContext()
            );
        } catch (\Exception $exception) {
            $marketplaceSeller = $sellerRepository->search(
                (new Criteria())->addFilter(new EqualsFilter('marketplace_seller.storeSlug', $customerId)),
                Context::createDefaultContext()
            );
        }

        $marketplaceSellerId = null;
        if ($marketplaceSeller && $marketplaceSeller->first()) {
            $marketplaceSellerId = $marketplaceSeller->first()->get('id');
        }

        return $marketplaceSellerId;
    }

    public function getShopwareProduct($productId)
    {
        $productRepository = $this->container->get('product.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('product.id', $productId));

        // get order data
        $product = $productRepository->search($criteria, Context::createDefaultContext())
            ->first();

        return $product;
    }

    public function getSellerProducts($eventData)
    {
        $context = Context::createDefaultContext();
        $params = null;
        if(isset($eventData['params'])) {

            $params = $eventData['params'];
        }
        $marketplaceProductRepository = $this->container->get('marketplace_product.repository');
        
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('marketplace_product.marketplace_seller.customer.id', $eventData['customerId']))
            ->addFilter(new EqualsFilter('marketplace_product.product.parentId', null))
            //->addFilter(new ProductAvailableFilter($eventData['salesChannelContext']->getSalesChannel()->getId(), ProductVisibilityDefinition::VISIBILITY_LINK))
            ->addAssociation('product')
            ->addAssociation('product.media')
            ->addAssociation('marketplace_seller')
            ->addAssociation('product.categories')
            ->addAssociation('product.manufacturer')
            ->addAssociation('product.prices')
            ->addAssociation('product.prices.rule')
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
           
        if(isset($eventData['profileProduct'])) {
            $criteria->addFilter(new EqualsFilter('product.active',1));
        }

        if (isset($params['limit']) && isset($params['page'])) {
            $criteria = $criteria->setLimit($params['limit'])
                ->setOffset(($params['page'] - 1) * $params['limit']);
        }
        // filter prodcts by name and status
        if (isset($params['status']) && $params['status'] != 'null') {
            $criteria->addFilter(new EqualsFilter('marketplace_product.product.active', $params['status']));
                    
        }

        if (isset($params['name']) && $params['name'] != 'null' && $params['name'] != '') {
            $term = (string) $params['name'];
            $criteria->addFilter(new ContainsFilter('marketplace_product.product.name', $params['name']));
        }

        // get object of product collection
        $productsCollection = $marketplaceProductRepository->search($criteria, $context)
            ->getElements();
        
        $groupedProductPlugin = $this->container->get('plugin.repository')->search((new Criteria())->addFilter(new EqualsFilter('name','WebkulMPGroupedProduct'))->addFilter(new EqualsFilter('active',1)),Context::createDefaultContext())->getElements();
        if($groupedProductPlugin){

            // get grouped products
            foreach($productsCollection as $key=>$product) {
                
                $groupedProductsTotal= $this->container->get('wk_mp_grouped_product.repository')->search((new Criteria())->addFilter(new EqualsFilter('productId', $product->get('productId')))->addAssociation('product'),Context::createDefaultContext())->getTotal();
                $groupedProducts = $this->container->get('wk_mp_grouped_product.repository')->search((new Criteria())->addFilter(new EqualsFilter('productId', $product->get('productId')))->addAssociation('product'),Context::createDefaultContext())->first();
                $groupedProductIds = [];
                if((int)$groupedProductsTotal > 0) {
                    
                    foreach($groupedProducts['groupedProduct'] as $groupedProduct) {
                        $groupedProductIds[] = $groupedProduct['productId'];
                        
                    }
                   
                    
                    $groupedProductsData = $this->container->get('product.repository')->search((new Criteria())->addFilter(new EqualsAnyFilter('id',$groupedProductIds))->addAssociation('options'),Context::createDefaultContext())->getElements();
                    $propertyGroupRepository = $this->container->get('property_group.repository');
                    $list = [];
                    foreach($groupedProductsData as $product){
                        $propertyData = [];
                       
                        if ($product->get('options')->getElements()) {
                            
                            $options = $product->get('options')->getElements();
                            
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
                        if ($product->get('parentId')) {
                            
                            $parentIdCollection[] = $product->get('parentId');
                            $parentId = $product->get('parentId');
                            $productRepository = $this->container->get('product.repository');
                            $productSw = $productRepository->search(
                                (new Criteria())
                                ->addFilter(new EqualsFilter('id', $parentId)),
                                Context::createDefaultContext()
                            )->first();
                            
                            $product->setName($productSw->get('name'));
                            if($product->getPrice() == null) {
                                $product->setPrice($productSw->get('price'));
                            }
                            
                            
                        }
                        foreach($groupedProducts['groupedProduct'] as $group){
                            if($product->get('id') == $group['productId']){
                              $defaultQty = $group['defaultQty'];
                            }
                        }
                       
                        $list[] = [
                            'id'=>$product->get('id'),
                            'name'=>$product->get('name'),
                            'propertyData'=>$propertyData,
                            'productNumber'=>$product->get('productNumber'),
                            'price' => $product->get('price')->getElements()[array_keys($product->get('price')->getElements())[0]]->getNet(),
                            'defaultQty'=>$defaultQty
                        ];
                       
                        
                        
                        $propertyData = [];
                    }
                    
                    $productsCollection[$key]['groupedProduct'] = $list;
                    $productsCollection[$key]['groupQty'] = $groupedProducts['groupedProduct'];
                }
            } 
        }
        
                                                        
        $countCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('marketplace_product.marketplace_seller.customer.id', $eventData['customerId']))
            ->addFilter(new EqualsFilter('marketplace_product.product.parentId', null));
            if(isset($eventData['profileProduct'])){
                $countCriteria->addFilter(new EqualsFilter('marketplace_product.product.active',1));
            }
            // filter prodcts by name and status
        if (isset($params['status']) && $params['status'] != 'null') {
            $countCriteria->addFilter(new EqualsFilter('marketplace_product.product.active', $params['status']));
                    
        }

        if (isset($params['name']) && $params['name'] != 'null' && $params['name'] != '') {
            $term = (string) $params['name'];
            $countCriteria->addFilter(new ContainsFilter('marketplace_product.product.name', $params['name']));
        }
        $productIds = $marketplaceProductRepository->searchIds($countCriteria, $context);
        $productCount = $productIds->getTotal();

        // collect product's variant
        foreach ($productsCollection as $productId => $product) {
            $productsCollection[$productId]->set('variants', $this->fetchProductVariantList($product->get('productId')));
        }
        

        $products = [
            'products' => $productsCollection,
            'total' => $productCount
        ];
        
        return $products;
    }

    public function createShopwareProduct($productDetails, SalesChannelContext $salesChannelContext)
    {
          
        $context = Context::createDefaultContext();
        $productRepository = $this->container->get('product.repository');
        
        // create product
        $id = !isset($productDetails['productId']) ? Uuid::randomHex() : $productDetails['productId'];
        $taxRepository = $this->container->get('tax.repository');
        $tax = $taxRepository->search((new Criteria())->addFilter(new EqualsFilter('id', $productDetails['tax'])), $context)->first();
        
        $netPrice = $productDetails['price']-($productDetails['price']*$tax->getTaxRate())/100;
        
        $data = [
            'id' => $id,
            'productNumber' => $productDetails['number'],
            'description' => htmlentities(htmlspecialchars(str_replace("<br>",'',$productDetails['description']))),
            'stock' => (int) $productDetails['stock'],
            'name' => $productDetails['name'],
            'price' => [
                [
                    'linked' => false,
                    'net' => (float) $netPrice,
                    'gross' => (float) $productDetails['price'],
                    'currencyId' => Defaults::CURRENCY,
                ]
            ],
            'manufacturer' => ['id' => $productDetails['manufacturer']],
            'tax' => [
                'id' => $productDetails['tax']
            ],
            'customFields'=> ['listing_status_checkbox'=> true]
        ];
        if($productDetails['maxPurchase'] > 0) {
            $data['maxPurchase'] = (int)$productDetails['maxPurchase'];
        } else {
            $data['maxPurchase'] = null;
        }
        
        if($productDetails['minPurchase'] > 0) {
            $data['minPurchase'] = (int)$productDetails['minPurchase'];
        } else {
            $data['minPurchase'] = (int)1;
        }
        
        if(isset($productDetails['groupedProductAddon']) && json_decode($productDetails['groupedProducts'])){ 
            
            $data['customFields'] = ['grouped_product_checkbox'=>true];
        }
        

        $categoryCollection = [];

        if (isset($productDetails['category']) && $productDetails['category']) {
            $productDetails['category'] = explode('&', $productDetails['category']);
            foreach ($productDetails['category'] as $category) {
                array_push($categoryCollection, ["id" => $category]);
            }

            $data['categories'] = $categoryCollection;
        }

        if (!isset($productDetails['productId'])) {
            $data['visibilities'] = [
                ['salesChannelId' => $salesChannelContext->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ];
        }

        if (isset($productDetails['cover'])) {
            $data['cover'] = [
                'id' => Uuid::randomHex(),
                'position' => 1,
                'media' => [
                    'id' => $productDetails['cover']['id'],
                    'title' => $productDetails['cover']['title'],
                ],
            ];
        }

        if (isset($productDetails['media'])) {
            $data['media'] = [];
            foreach ($productDetails['media'] as $mediaDetails) {
                $media = [
                    'id' => Uuid::randomHex(),
                    'media' => [
                        'id' => $mediaDetails['id'],
                        'title' => $mediaDetails['title'],
                    ],
                ];

                array_push($data['media'], $media);
            }
        }

        if (isset($productDetails['active'])) {
            $data['active'] = $productDetails['active'];
        }

        try {
           
            $productRepository->upsert([$data], $context);

             // get last created product with the help of id
            $criteria = (new Criteria([$id]))
                ->addAssociation('cover');

            $createdProductCollection = $productRepository->search($criteria, $context);
            $createdProduct = $createdProductCollection->first();


            $response = [
                'status' => true,
                'createdProduct' => $createdProduct,
            ];
        } catch (\Exception $exception) {
            $response = [
                'status' => false,
                //'exceptionCode' => $exception->getErrorCode(),
                'exceptionMessage' => $exception->getMessage(),
            ];
        }
        
        return $response;
    }
    public function updateAdvancedPrice($data, SalesChannelContext $salesChannelContext)
    {
        if ($data['pricingRules']) {
            $productPriceRepository = $this->container->get('product_price.repository');
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('productId',$data['productId']));
            $productPriceEntity = $productPriceRepository->search($criteria,$salesChannelContext->getContext())->getElements();
            
            if($productPriceEntity){
                foreach ($productPriceEntity as $productPrice) {
                    
                    $productPriceRepository->delete([['id'=>$productPrice->getId()]],$salesChannelContext->getContext());
                }
            }
            
            $advancedPrices = json_decode($data['pricingRules']);
            
            foreach ($advancedPrices as $advancedPrice) {
                $prices = $advancedPrice->prices;
                
                foreach ($prices as $price) {
                    
                    $priceJson = [Defaults::CURRENCY =>['net'=>(float)$price->price[0]->net,'gross'=>(float)$price->price[0]->gross,'linked'=>true,'currencyId'=>Defaults::CURRENCY]];
                    
                    $formattedPrices = [
                        'versionId' => Defaults::LIVE_VERSION,
                        'ruleId' => $advancedPrice->ruleId,
                        'productId' => $data['productId'],
                        'productVersionId' => Defaults::LIVE_VERSION,
                        'price' => $priceJson,
                        'quantityStart' => (int)$price->quantityStart,
                    ];
                    if($price->quantityEnd) {
                        $formattedPrices['quantityEnd'] = (int)$price->quantityEnd;
                    }
                    try{

                        $productPriceRepository->create([$formattedPrices],$salesChannelContext->getContext());
                    } catch(Exception $exception){
                        
                    }
                }
            }
        }
        
        return;
        
    }

    public function updateProductVariants($variantCollection)
    {
        $context = Context::createDefaultContext();
        $productRepository = $this->container->get('product.repository');

        $variantIds = [];
        foreach ($variantCollection as $variant) {
            $id = Uuid::randomHex();
            $variantIds[] = $id;

            $variant['id'] = $id;
            $variant['active'] = true;

            $options = $variant['options'];
            $variant['options'] = [];

            foreach($options as $option) {
                array_push($variant['options'], [
                    'id' => $option
                ]);
            }

            $productRepository->create([$variant], $context);
        }

        return [
            'status' => true,
            'variantIds' => $variantIds
        ];
    }

    public function updateProductVariant($variantDetails)
    {
        $context = Context::createDefaultContext();
        $productRepository = $this->container->get('product.repository');
        
        $variant = [
            'active' => true,
            'stock' => (int) $variantDetails['stock'],
            'price' => [
                [
                    'linked' => false,
                    'net' => (float) $variantDetails['price'],
                    'gross' => (float) $variantDetails['price'],
                    'currencyId' => Defaults::CURRENCY,
                ]
            ],
            'id' => $variantDetails['variantId'],
        ];

        $productRepository->upsert([$variant], $context);

        return true;
    }

    public function updateShopwareProduct($productDetails, $context)
    {
        
        $productRepository = $this->container->get('product.repository');

        $id = $productDetails['id'];
        $data = [
            'id' => $id,
            'name' => $productDetails['name'],
        ];
        $updatedProduct = $productRepository->upsert([$data], $context);

        // get last created product with the help of id
        $updatedProductCollection = $productRepository->search(new Criteria([$id]), $context);
        $updatedProduct = $updatedProductCollection->first();

        return $updatedProduct;
    }

    public function deleteShopwareProduct($productId)
    {   
       
        $productRepository = $this->container->get('product.repository');
        $marketplaceProductRepository = $this->container->get('marketplace_product.repository');
        if (is_array($productId)) {
            $orderlineItem = $this->container->get('order_line_item.repository')->search((new Criteria())->addFilter(new EqualsAnyFilter('productId',$productId)), Context::createDefaultContext())->getElements();
        } else {
            $orderlineItem = $this->container->get('order_line_item.repository')->search((new Criteria())->addFilter(new EqualsFilter('productId',$productId)), Context::createDefaultContext())->getElements();
        }
       
        if ($orderlineItem) {
            foreach($orderlineItem as $lineItem) {
                if (in_array($lineItem->getProductId(), $productId)) {
                    $productIdArr[] = $lineItem->getProductId(); 
                    $orderProductIds[] = ['id' => $lineItem->getProductId(), "active" => false];
                }
            }
            $productRepository->upsert($orderProductIds, Context::createDefaultContext());
            $nonOrderProductIds = array_diff($productId, $productIdArr);
            if($nonOrderProductIds) {
                $marketplaceProductId = $marketplaceProductRepository->searchIds((new Criteria())->addFilter(new EqualsAnyFilter('productId',$nonOrderProductIds)),Context::createDefaultContext())->getData();
                
                $productId = $this->getProductIdArray(array_keys($marketplaceProductId));

                $marketplaceProductRepository->delete($productId, Context::createDefaultContext());
            }
           
           
        } 
        else{
            
                if(is_array($productId)) {
                    
                    $marketplaceProductId = $marketplaceProductRepository->searchIds((new Criteria())->addFilter(new EqualsAnyFilter('productId',$productId)),Context::createDefaultContext())->getData();
                    
                    $ids = $this->getProductIdArray(array_keys($marketplaceProductId));
                    $marketplaceProductRepository->delete($ids, Context::createDefaultContext());    
                }  else {
                    $marketplaceProductId = $marketplaceProductRepository->search((new Criteria())->addFilter(new EqualsFilter('productId',$productId)),Context::createDefaultContext())->first()->getId();
                    
                    $ids = $this->getProductIdArray(array_keys($marketplaceProductId));
                    $marketplaceProductRepository->delete($ids, Context::createDefaultContext());
                }
               
            }
        return true;
    }
        
    

    public function getProductIdArray($productId)
    {
        $idArray = [];

        foreach ($productId as $id) {
            $idArray[] = ['id' => $id]; 
        }
        return $idArray;
    }
    public function fetchSellerOrders($sellerId, $languageId, $salesChannelId, ? array $params)
    {
        $context = Context::createDefaultContext();
        $marketplaceOrderRepository = $this->container->get('marketplace_commission.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('marketplace_commission.marketplace_order.marketplace_product.marketplace_seller.customerId', $sellerId))
            ->addAssociation('marketplace_order.order_line_item')
            //->addAssociation('marketplace_order.order')
            ->addAssociation('marketplace_order.order.addresses')
            ->addAssociation('marketplace_order.order.deliveries.shippingMethod')
            ->addAssociation('marketplace_order.order.addresses.orderDeliveries')
            ->addAssociation('marketplace_order.order.addresses.orderDeliveries.shippingOrderAddress.country')
            ->addAssociation('marketplace_order.order.addresses.orderDeliveries.shippingOrderAddress.countryState')
            ->addAssociation('marketplace_order.order.addresses.countryState')
            ->addAssociation('marketplace_order.order.addresses.country')
            ->addAssociation('marketplace_order.marketplace_product')
            ->addAssociation('marketplace_order.order_line_item.cover')
            ->addAssociation('marketplace_order.order_line_item.order')
            ->addAssociation('marketplace_order.order_line_item.order.transactions')
            ->addAssociation('marketplace_order.marketplace_product.marketplace_seller')
            ->addAssociation('marketplace_order.order_line_item.order.transactions.paymentMethod')
            ->addAssociation('marketplace_order.currency')
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        if (isset($params['page']) && isset($params['limit'])) {
            $criteria = $criteria->setLimit($params['limit'])
                ->setOffset(($params['page'] - 1) * $params['limit']);
        }

        // get object of orders collection
        $marketplaceOrdersCollection = $marketplaceOrderRepository->search($criteria, $context)
            ->getEntities()
            ->getElements();
       
        $marketplaceOrders = [];
        foreach ($marketplaceOrdersCollection as $order) {
            $marketplaceOrder = $order->get('marketplace_order');
            $currencySymbol = $order->get('marketplace_order')->get('currency')->getSymbol();
           $marketplaceOrder->set('symbol', $currencySymbol);
            $marketplaceOrder->set('earning', $order->get('sellerEarning'));
            $marketplaceOrder->set('commission', $order->get('commissionAmount'));

            array_push($marketplaceOrders, $marketplaceOrder);
        }
        // get orders count
        $countCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('marketplace_commission.marketplace_order.marketplace_product.marketplace_seller.customerId', $sellerId));

        $productIds = $marketplaceOrderRepository->searchIds($countCriteria, $context);
        $orderCount = $productIds->getTotal();
        // order state 
        $stateMachineRepo = $this->container->get('state_machine.repository');
        $state = $stateMachineRepo->searchIds((new Criteria())->addFilter(new EqualsFilter('technicalName', 'order.state')), Context::createDefaultContext());
        $orderStateIds = $this->getSystemConfigurationValue('WebkulMVMarketplace.config.orderStateIds', $salesChannelId);
        $orderState = [];
        if ($orderStateIds) {

            $orderState = $this->container->get('state_machine_state_translation.repository')->search((new Criteria())->addAssociation('state_machine_state')->addFilter(new EqualsFilter('languageId', $languageId))->addFilter(new EqualsAnyFilter('stateMachineState.id',$orderStateIds)), Context::createDefaultContext())->getElements();
        }
        
        // collection of orders and total count
        $orders = [
            'orders' => $marketplaceOrders,
            'total' => $orderCount,
            'orderState'=> $orderState
        ];

        return $orders;
    }

    public function getShopwareOrder($orderId)
    {
        $orderRepository = $this->container->get('order.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('order.id', $orderId))
            ->addAssociation('lineItems')
            ->addAssociation('transactions')
            ->addAssociation('transactions.paymentMethod');

        // get order data
        $order = $orderRepository->search($criteria, Context::createDefaultContext())
            ->first();

        return $order;
    }

    public function getMarketplaceProduct($productId)
    {
        $marketplaceProductsRepository = $this->container->get('marketplace_product.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('marketplace_products.id', $productId));

        // get order data
        $product = $marketplaceProductsRepository->search($criteria, Context::createDefaultContext())
            ->first();

        return $product;
    }

    public function getSystemConfigurationValue($configurationkey, $salesChannelId = null)
    {
        if(is_object($salesChannelId)) {
            $salesChannelId = $salesChannelId->getSalesChannel()->getDomains()->first()->getSalesChannelId();
            }
        return $this->systemConfigService->get($configurationkey, $salesChannelId);
    }

    public function uploadProductMedia($productMedia, $salesChannelContext)
    {
        $context = Context::createDefaultContext();
        $mediaRepository = $this->container->get('media.repository');

        $mediaIds = [];
        foreach ($productMedia as $file) {
            $isImageVerified = $this->verifyImage($file);

            if ($isImageVerified) {
                $fileName = $file->getClientOriginalName();

                $explodedFileName = explode('.', $fileName);
                unset($explodedFileName[count($explodedFileName) - 1]);
                $fileName = $salesChannelContext->getCustomer()->getId() . '_' . implode('.', $explodedFileName);

                $mediaId = Uuid::randomHex();
                $media = [
                    [
                        'id' => $mediaId,
                        'name' => $fileName,
                        'fileName' => $fileName,
                        'mimeType' => $file->getClientMimeType(),
                        'fileExtension' => $file->guessExtension(),
                    ]
                ];

                $mediaId = $mediaRepository->create($media, Context::createDefaultContext())
                    ->getEvents()
                    ->getElements()[1]
                    ->getIds()[0];
                if (is_array($mediaId)) {
                    $mediaId = $mediaId['mediaId'];
                }
            
                try {
                    $this->upload($file, $fileName, $mediaId, $context);
                } catch (\Exception $exception) {
                    $fileName = $fileName . $mediaId;
                    $this->upload($file, $fileName, $mediaId, $context);
                }

                array_push($mediaIds, [
                    'id' => $mediaId,
                    'title' => $fileName
                ]);
            } else {
                return  false;
            }
        }
        return $mediaIds;
    }
    public function uploadSellerProfileMedia($file, $salesChannelContext)
    {
        $context = Context::createDefaultContext();
        $mediaRepository = $this->container->get('media.repository');

            $isImageVerified = $this->verifyImage($file);

            if ($isImageVerified) {
                $fileName = $file->getClientOriginalName();
                
                $explodedFileName = explode('.', $fileName);
                unset($explodedFileName[count($explodedFileName) - 1]);
                $fileName = $salesChannelContext->getCustomer()->getId() . '_' . implode('.', $explodedFileName);
                
                $mediaId = Uuid::randomHex();
                $media = [
                    [
                        'id' => $mediaId,
                        'name' => $fileName,
                        'fileName' => $fileName,
                        'mimeType' => $file->getClientMimeType(),
                        'fileExtension' => $file->guessExtension(),
                    ]
                ];

                $mediaId = $mediaRepository->create($media, Context::createDefaultContext())
                    ->getEvents()
                    ->getElements()[1]
                    ->getIds()[0];
                if (is_array($mediaId)) {
                    $mediaId = $mediaId['mediaId'];
                }
            
                try {
                    $this->upload($file, $fileName, $mediaId, $context);
                } catch (\Exception $exception) {
                    $fileName = $fileName . $mediaId;
                    $this->upload($file, $fileName, $mediaId, $context);
                }

                
            }
        return $mediaId;

    }

    public function getMarketplaceSellers($sellerIds = null)
    {
        $sellerRepository = $this->container->get('marketplace_seller.repository');
        $criteria = new Criteria();
        $criteria->addAssociation('mediaLogo');
        $hyperlocalPlugin = $this->container->get('plugin.repository')->search((new Criteria())->addFilter(new EqualsFilter('name','WebkulMPHyperlocal'))->addFilter(new EqualsFilter('active',1)),Context::createDefaultContext())->getElements();
        
        if($hyperlocalPlugin) {
            $criteria->addFilter(new EqualsAnyFilter('id', $sellerIds));
        }
        // get customer from marketplace_seller entity
        $marketplaceSellers = $sellerRepository
            ->search($criteria, Context::createDefaultContext())
            ->getElements();
        
        return $marketplaceSellers;
    }
    

    public function upload($file, $fileName, $mediaId, $context)
    {
       
        return $this->mediaUpdater->persistFileToMedia(
            new MediaFile(
                $file->getRealPath(),
                $file->getMimeType(),
                $file->guessExtension(),
                $file->getSize()
            ),
            $this->fileNameProvider->provide(
                $fileName,
                $file->getExtension(),
                $mediaId,
                $context
            ),
            $mediaId,
            $context
        );
    }

    public function updateProductConfiguratorSettings($configuratorSettings)
    {
        $settings = [];
        $productId = $configuratorSettings['productId'];
        $configuratorSettingsRepository = $this->container->get('product_configurator_setting.repository');

        foreach ($configuratorSettings['options'] as $setting) {
            $settings[] = [
                'productId' => $productId,
                'optionId' => $setting,
            ];
        }

        $configuratorSettingsRepository->create($settings, Context::createDefaultContext());

        return true;
    }

    public function getSelectedGroupOptions($productId)
    {
        $configuratorRepository = $this->container->get('product_configurator_setting.repository');

        $criteria = (new Criteria())
            ->addAssociation('option')
            ->addFilter(new EqualsFilter('productId', $productId));

        $options = $configuratorRepository->search($criteria, Context::createDefaultContext());

        $optedOptions = [];
        if ($options->getTotal()) {
            $options = $options->getEntities()->getElements();

            foreach ($options as $option) {
                $optedOptions[$option->get('option')->get('groupId')][] = $option->get('option')->get('id');
            }
        }

        return $optedOptions;
    }

    public function fetchProductVariantList($productId)
    {
        $productRepository = $this->container->get('product.repository');

        $variantCriteria = (new Criteria())
        ->addFilter(new EqualsFilter('parentId', $productId));

        return $productRepository->search($variantCriteria, Context::createDefaultContext())
            ->getEntities()
            ->getElements();
    }

    public function createMarketplaceProduct($productId, $marketplaceSellerId)
    {
        $marketplaceProductRepository = $this->container->get('marketplace_product.repository');

        // map created product with marketplace_product entity
        $marketplaceProductRepository->create([[
            "productId" => $productId,
            "marketplaceSellerId" => $marketplaceSellerId,
        ]], Context::createDefaultContext());

        return true;
    }
    public function sendLowStockMail($productName, $customerId, $salesChannelId) 
    {
        $salesChannel = $this->container->get('sales_channel.repository')->search((new Criteria())->addFilter(new EqualsFilter('id',$salesChannelId)),Context::createDefaultContext())->first();
        $data = new DataBag();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customer.id', $customerId));
        $customer = $this->container->get('customer.repository')->search($criteria,  Context::createDefaultContext())->first();
       
            $customerEmail =  $customer->getEmail();
            $customerFirstName = $customer->getFirstName();
            $customerLastName = $customer->getLastName();
            $salesChannelId = $customer->getsalesChannelId();

        
        $sellerName = $customerFirstName . ' ' . $customerLastName;
        $data->set(
            'recipients',
            [
                $customerEmail => $sellerName
            ]
        );
        $mailTemplate = $this->container->get('marketplace_email_template.repository')->search((new Criteria())->addFilter(new EqualsFilter('technicalName', 'low_stock_mail')),Context::createDefaultContext())->first();
        
        if($mailTemplate){
            
            $subject = $mailTemplate['subject'];
            $message = $mailTemplate['message'];
            $message = str_replace('{config_name}', $salesChannel->getTranslated()['name'], $message);
            $message = str_replace('{seller_name}', $sellerName , $message);
            $message = str_replace('{product_name}', $productName, $message);
            
            $data->set('senderName', $salesChannel->getTranslated()['name']);
            $data->set('salesChannelId', $salesChannelId);
            $data->set('subject', $subject);
            $data->set('contentHtml', $message);
            $data->set('contentPlain', strip_tags($message));
            try{
               return $this->mailService->send($data->all(), Context::createDefaultContext());
            }catch(Exception $ex){
                
            }
            
        }
        return;
                
    }
    public function getTodayOrder($customerId)
    {
        $context = Context::createDefaultContext();
         
        $marketplaceOrderRepository = $this->container->get('marketplace_commission.repository');
        //$dateTime = new \DateTime('now');
        $currentDate = date('Y-m-d');
        
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('marketplace_commission.marketplace_order.marketplace_product.marketplace_seller.customerId', $customerId))
            ->addFilter(new RangeFilter('createdAt', ['gte'=>$currentDate]));
        $todayOrder = $marketplaceOrderRepository->search($criteria, $context)->getTotal();
                 
        return $todayOrder;


    }
    public function getTodaySales($customerId)
    {
        $context = Context::createDefaultContext();
        $marketplaceOrderRepository = $this->container->get('marketplace_commission.repository');
       
        $totalGrossSale = 0;
        $totalSellerIncome = 0;
        $totalAdminCommission = 0;
        $todayTurnover = 0;
       
        $dateTime = new \DateTime('now');
        $currentDate = $dateTime->format(Defaults::STORAGE_DATE_FORMAT);
       
        $criteria = (new Criteria())->addFilter(new EqualsFilter('marketplace_commission.marketplace_order.marketplace_product.marketplace_seller.customerId', $customerId))
        ->addFilter(new RangeFilter('createdAt',['gte'=>$currentDate] ))
        ->addAssociation('marketplace_order')
        ->addAssociation('marketplace_order.order');
        $todaySales = $marketplaceOrderRepository->search($criteria,$context)->getElements();
        
        foreach($todaySales  as $sale) {
            $todayTurnover += $sale->get('marketplace_order')->get('order')->get('amountTotal');
        }
        $overallSaleCriteria = (new Criteria())->addFilter(new EqualsFilter('marketplace_commission.marketplace_order.marketplace_product.marketplace_seller.customerId', $customerId))
        ->addAssociation('marketplace_order')
        ->addAssociation('marketplace_order.order');
        $overallSales = $marketplaceOrderRepository->search($overallSaleCriteria, $context)->getElements();
       
        foreach($overallSales as $totalSale) {
            
            $totalSellerIncome += $totalSale->get('sellerEarning');
            $totalAdminCommission += $totalSale->get('commissionAmount');
            $totalGrossSale += $totalSale->get('marketplace_order')->get('order')->get('positionPrice');

        }
        return ['totalGrossSales'=>$totalGrossSale, 'adminCommission'=>$totalAdminCommission, 'sellerIncome'=>$totalSellerIncome,'todayTurnover'=>$todayTurnover];
    }
    public function getNewBuyers($customerId) 
    {
        $dateTime = new \DateTime('now');
        $currentDate = $dateTime->format(Defaults::STORAGE_DATE_FORMAT);
      
        $criteria = (new Criteria())->addFilter(new EqualsFilter('marketplace_order.marketplace_product.marketplace_seller.customerId', $customerId))
        ->addFilter(new RangeFilter('createdAt', ['gte' => $currentDate]))
        ->addAssociation('order')
        ->addAssociation('order.order_customer');
        $marketplaceOrderRepository = $this->container->get('marketplace_order.repository');
        $orderCustomer = $marketplaceOrderRepository->search($criteria, Context::createDefaultContext())->getElements();
        $customerEmail = array();
        foreach($orderCustomer as $customer) {
            $customerEmail[] = $customer->get('order')->get('orderCustomer')->get('email');
        }
        
       return count(array_unique($customerEmail));
    }
    public function getLowStock($customerId, $salesChannelContext)
    {
        $criteria = (new Criteria())
        ->addFilter(new EqualsFilter('marketplace_product.marketplace_seller.customerId', $customerId))
        ->addFilter(new EqualsFilter('marketplace_product.product.parentId', null))
        ->addAssociation('marketplace_seller')
        ->addAssociation('product');
        $products = $this->container->get('marketplace_product.repository')->search($criteria, Context::createDefaultContext())->getElements();
        $lowStockConfig = $this->getSystemConfigurationValue('WebkulMVMarketplace.config.lowStockCount', $salesChannelContext );
        $lowStockProductNo = 0;
        
        foreach ($products as $product) {
            
            if ($product->get('product')->get('availableStock') < $lowStockConfig) {
                $lowStockProductNo++;
            }
        }
       
        return $lowStockProductNo;
    }
    public function getOrderState($customerId)
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('marketplace_product.marketplace_seller.customerId', $customerId))
        ->addAssociation('marketplace_product')
        ->addAssociation('marketplace_product.marketplace_Seller');
        $orders = $this->container->get('marketplace_order.repository')->search($criteria, Context::createDefaultContext());
        $totalOrders = $orders->getTotal();
        $orderState = [];
        
        foreach ($orders as $order) {
            $orderState[] = $order->get('state_machine_state')->get('technicalName');
        }
       return  array_merge(array_count_values($orderState),['orderCount'=>$totalOrders]);
    }
    public function orderGraphData($customerId)
    {
        $context = Context::createDefaultContext();
         
        $marketplaceOrderRepository = $this->container->get('marketplace_commission.repository');
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('marketplace_commission.marketplace_order.marketplace_product.marketplace_seller.customerId', $customerId))
            ->addFilter(new RangeFilter('createdAt', ['gte'=> date('Y-m-d',strtotime("-30 days"))]));
        $graphOrder = $marketplaceOrderRepository->search($criteria, $context);
        $graphOrderDate = [];
        foreach($graphOrder as $order) {
            $graphOrderDate[] = $order->get('createdAt')->format(Defaults::STORAGE_DATE_FORMAT);
        }
        
       return array_count_values($graphOrderDate);
       
    }
    public function turnoverGraphData($eventData)
    {   
        $customerId = $eventData['customerId'];
        $currencyFactor = $eventData['salesChannelContext']->getCurrency()->getFactor();
        $context = Context::createDefaultContext();
        $marketplaceOrderRepository = $this->container->get('marketplace_commission.repository');
        $criteria = (new Criteria())->addFilter(new EqualsFilter('marketplace_commission.marketplace_order.marketplace_product.marketplace_seller.customerId', $customerId))
        ->addFilter(new RangeFilter('createdAt', ['gte'=> date('Y-m-d',strtotime("-30 days"))]))
        ->addAssociation('marketplace_order')
        ->addAssociation('marketplace_order.order');
        $sales = $marketplaceOrderRepository->search($criteria, $context)->getElements();
        
        $salesRecord = array();
        foreach($sales as $sale)
        {
            
            $salesRecord[$sale->get('marketplace_order')->get('order')->get('orderDate')->format(DEFAULTS::STORAGE_DATE_FORMAT)][] = $sale->get('marketplace_order')->get('order')->get('amountTotal');
        }
        
        $salesAmount=[];
        foreach($salesRecord as $date=>$value) {
                
                $salesAmount[$date] = array_sum($value); 
           
        }
        foreach($salesAmount as $date=>$amount) {
            $salesAmount[$date] = $currencyFactor*$amount;
        }
        
       return $salesAmount;
    }
    public function updateSellerOrder($params)
    {
        
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id',$params['mpOrderId']));
        $mpOrderRepository = $this->container->get('marketplace_order.repository');
        $mpOrder = $mpOrderRepository->search($criteria, Context::createDefaultContext())->first();
        $lineItemRepository = $this->container->get('order_line_item.repository');
        $lineCriteria = new Criteria();
        $lineCriteria->addFilter(new EqualsFilter('id', $mpOrder['orderLineItemId']));
        $lineItem = $lineItemRepository->search($lineCriteria, Context::createDefaultContext())->first();
        
        $data = [
            'id' => $params['mpOrderId'],
            'orderStatus' => $params['orderStateId'],
        ];

         $mpOrderRepository->upsert([$data], Context::createDefaultContext());
         /* sending mail to customer on update order status*/
         
        $updatedOrder = $mpOrderRepository->search((new Criteria())->addFilter(new EqualsFilter('orderId', $lineItem->getOrderId())), Context::createDefaultContext())->getElements();
        foreach($updatedOrder as $order) {
            $orderStatus[] = $order['orderStatus'];
            
        }
        $statusCountValue = array_count_values($orderStatus);
        $stateMachineRepository = $this->container->get('state_machine.repository');
        $orderStateMachineId = $stateMachineRepository->search((new Criteria())->addFilter(new EqualsFilter('technicalName','order.state')),Context::createDefaultContext())->first()->getId();
        $stateMachineStateEntity = $this->container->get('state_machine_state.repository')->search((new Criteria())->addFilter(new EqualsFilter('stateMachineId',$orderStateMachineId))->addFilter(new EqualsAnyFilter('technicalName',['completed','cancelled'])),Context::createDefaultContext())->getElements();
        foreach ($stateMachineStateEntity as $state) {
            if ($state->getTechnicalName() == 'completed') {
                $completeStateId =    $state->getId();
            }
        }
        $allowedOrderStatusIdArray = array_keys($stateMachineStateEntity);
        $mainOrderStatusUpdate = true;
        foreach($statusCountValue as $key=>$value) {
           
            if(in_array($key,$allowedOrderStatusIdArray) == false) {
                $mainOrderStatusUpdate = false;
            }
        }
        
        if ($mainOrderStatusUpdate && array_key_exists($completeStateId,$statusCountValue) && count(array_unique($orderStatus)) != 1) {
            
            $orderData = [
                'id'=> $lineItem->getOrderId(),
                'stateId'=>$completeStateId
            ];
            $this->container->get('order.repository')->upsert([$orderData], Context::createDefaultContext());
        }
        if (count(array_unique($orderStatus)) == 1) {
            $orderData = [
                'id'=> $lineItem->getOrderId(),
                'stateId'=>$params['orderStateId']
            ];
            $this->container->get('order.repository')->upsert([$orderData], Context::createDefaultContext());
        }
        
       return true;

    }
    public function sendMailOnUpdateOrder($params,$salesChannelId) 
    {
        
        $mpOrderId = $params['mpOrderId'];
        $data = new DataBag();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $mpOrderId));
        $criteria->addAssociation('order.customer');
        $criteria->addAssociation('marketplace_product.marketplace_seller');
        $order = $this->container->get('marketplace_order.repository')->search($criteria,  Context::createDefaultContext())->first();
        
            $storeSlug = $order['marketplace_product']['marketplace_seller']['storeSlug'];
            $storeOwner = $order['marketplace_product']['marketplace_seller']['storOwner'];
            $orderNo = $order['order']->getOrderNumber();
            $customer = $order['order']->getOrderCustomer();
           
            $customerEmail =  $customer->getEmail();
            $customerFirstName = $customer->getFirstName();
            $customerLastName = $customer->getLastName();
            $customerFullName = $customerFirstName . ' ' . $customerLastName;
        $data->set(
            'recipients',
            [
                $customerEmail => $customerFirstName . ' ' . $customerLastName
            ]
        );
        $mailTemplate = $this->container->get('marketplace_email_template.repository')->search((new Criteria())->addFilter(new EqualsFilter('technicalName', 'update_order_status')),Context::createDefaultContext())->first();
        $result = null;
        if($mailTemplate){
            
            $subject = $mailTemplate['subject'];
            $message = $mailTemplate['message'];
            $message = str_replace('{config_name}', $storeSlug, $message);
            $message = str_replace('{config_owner}', $customerFullName, $message);
            $message = str_replace('{order}', $orderNo, $message);
            
            $data->set('senderName', 'admin');
            $data->set('salesChannelId', $salesChannelId);
            $data->set('subject', $subject);
            $data->set('contentHtml', $message);
            $data->set('contentPlain', strip_tags($message));
            $result = $this->mailService->send($data->all(), Context::createDefaultContext());
        }
        return $result;
    }
    public function createGroupedProduct($productId,$groupedProduct){
        
        $data = ['productId'=>$productId, 'groupedProduct'=>$groupedProduct];
        $existingData = $this->container->get('wk_mp_grouped_product.repository')->search((new Criteria())->addFilter(new EqualsFilter('productId',$productId)),Context::createDefaultContext())->first();
        
        if($existingData){
            $data['id'] = $existingData->get('id');
        } else {
            $data['id'] = Uuid::randomHex();
        }
         $this->container->get('wk_mp_grouped_product.repository')->upsert([$data],Context::createDefaultContext());
        return true;
    }
   
}
