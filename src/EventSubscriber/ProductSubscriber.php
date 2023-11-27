<?php

namespace Webkul\MultiVendor\EventSubscriber;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Webkul\MultiVendor\Event\MarketplaceEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSubscriber implements EventSubscriberInterface
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

    public function fetchSellerProducts(MarketplaceEvent $marketplaceEvent)
    {
        $eventData = $marketplaceEvent->getEventData();
        
        $dataFormatter = $this->container->get('marketplace.data.format');
        $storefrontHelper = $this->container->get('storefront.helper');
        $currentCurrencyId = $eventData['salesChannelContext']->getCurrencyId();
        $currency = $this->container->get('currency.repository')->search((new Criteria())->addFilter(new EqualsFilter('id',$currentCurrencyId)),Context::createDefaultContext())->first();
        // get products of seller
        $productsCollection = $storefrontHelper->getSellerProducts($eventData);
        
        $formattedProductsCollection = [];
        foreach ($productsCollection['products'] as $key => $marketplaceProduct) {

            $formattedProductDetails = $dataFormatter->formatProductDetails($marketplaceProduct['product']);
            
            $formattedProductDetails['variants'] = $dataFormatter->formatProductVariants($marketplaceProduct->get('variants'), $formattedProductDetails['price']);

            $formattedProductDetails['productId'] = $marketplaceProduct->get('productId');
            if(isset($marketplaceProduct->get('product')->getCustomFields()['listing_status_checkbox'])){
                $listingStatus = $marketplaceProduct->get('product')->getCustomFields()['listing_status_checkbox'];
            } else{
                $listingStatus = false;
            }
            // store grouped product
            if($marketplaceProduct->get('groupedProduct')){
                
               $formattedProductDetails['groupedProduct'] = $marketplaceProduct->get('groupedProduct');
            }
            $formattedProductDetails['listingStatus'] = $listingStatus;
            $formattedProductsCollection[$key] = $formattedProductDetails;
        }
        if (isset($eventData['salesChannelContext'])) {
            $deleteOption = $storefrontHelper->getSystemConfigurationValue('WebkulMVMarketplace.config.deleteOption', $eventData['salesChannelContext']);
        }
        else{
            $deleteOption = null;
        }
        $membershipPlugin = $this->container->get('plugin.repository')->search((new Criteria())->addFilter(new EqualsFilter('name', 'WebkulMPMembership'))->addFilter(new EqualsFilter('active',1)),Context::createDefaultContext())->first();
        $groupedProductPlugin = $this->container->get('plugin.repository')->search((new Criteria())->addFilter(new EqualsFilter('name', 'WebkulMPGroupedProduct'))->addFilter(new EqualsFilter('active',1)),Context::createDefaultContext())->first();
        if($membershipPlugin) {
            $membershipAddon = true;
        } else {
            $membershipAddon = false;
        }
        if($groupedProductPlugin) {
            $groupedProductPlugin = true;
        } else {
            $groupedProductPlugin = false;
        }
        
        $storefrontHelper = $this->container->get('storefront.helper');
        $changeProductStatus = $storefrontHelper->getSystemConfigurationValue('WebkulMVMarketplace.config.allowChangeProductStatus', $eventData['salesChannelContext']);
        $marketplaceEvent->setEventResponse([
            'status' => true,
            'total' => $productsCollection['total'],
            'productsCollection' => $formattedProductsCollection,
            'deleteStatus' =>$deleteOption,
            'membershipAddon' => $membershipAddon,
            'groupedProductAddon' => $groupedProductPlugin,
            'changeProductStatus' => $changeProductStatus,
            'currency' => $currency
        ]);

        return;

    }

    public function createMarketplaceProduct(MarketplaceEvent $marketplaceEvent)
    {
        
        $eventData = $marketplaceEvent->getEventData();
        
        $productMedia = $eventData['productMedia'];
        $productDetails = $eventData['productDetails'];
        // check Stock range in int(11)
        if($productDetails['stock'] > 2147483647 || $productDetails['price'] > 2147483647) {
            $response = ['status'=> false, 'message'=> 'Stock or price must be less then 2147483648','code'=>400];
            $marketplaceEvent->setEventResponse($response);
                return;
        }
        // check membership restriction
        $membershiPlugin = $this->container->get('plugin.repository')->search((new Criteria())->addFilter(new EqualsFilter('name','WebkulMPMembership'))->addFilter(new EqualsFilter('active',1)), context::createDefaultContext())->first();
        
        if($membershiPlugin) {
            $membershipHelper = $this->container->get('membership.helper');
            $checkExpiry = $membershipHelper->checkMembershipStatus($eventData['customerId']);
           
            if($checkExpiry['status'] === false) {
                $response = ['status'=> false, 'message' => $checkExpiry['msg'], 'code'=> 400];
                $marketplaceEvent->setEventResponse($response);
                return;
            }
            $checkMembership = $membershipHelper->checkProductQuantityPrice($eventData);
            if($checkMembership) {

                if($checkMembership['status'] === false) {
                    $response = ['status'=> false, 'message' => $checkMembership['msg'], 'code'=> 400];
                    $marketplaceEvent->setEventResponse($response);
                    return;
                }
            }
            
            
        }
        
        $storefrontHelper = $this->container->get('storefront.helper');
        $dataFormatter = $this->container->get('marketplace.data.format');
        $autoApproveProduct = $storefrontHelper->getSystemConfigurationValue('WebkulMVMarketplace.config.productAutoApprove', $eventData['salesChannelContext']);
        $productDetails['active'] = $autoApproveProduct ? true : false;

        if (!empty($productMedia)) {
            $productDetails['media'] = $storefrontHelper->uploadProductMedia($productMedia, $eventData['salesChannelContext']);
            if($productDetails['media'] == false) {
                $translator = $this->container->get('translator');
                
                $response = ['status'=> false, 'message' => $translator->trans('wk-mp.product.create.imageSizeErrorMessage'), 'code'=> 400];
                $marketplaceEvent->setEventResponse($response);
                return;
            }
            if (!empty($productDetails['media'])) {
                $productDetails['cover'] = $productDetails['media'][0];
                unset($productDetails['media'][0]);
            } else {
                unset($productDetails['media']);
            }
        }
        
        // create shopware product
        $shopwareProduct = $storefrontHelper->createShopwareProduct($productDetails, $eventData['salesChannelContext']);

        $response = $shopwareProduct;
        if ($shopwareProduct['status']) {
            // map created product with marketplace_product entity

            $productId = $shopwareProduct['createdProduct']->getId();
            $marketplaceSellerId = $storefrontHelper->getSellerId($eventData['customerId']);

            $storefrontHelper->createMarketplaceProduct($productId, $marketplaceSellerId);
            // create grouped product
            if(isset($productDetails['groupedProductAddon'])){

                if(json_decode($productDetails['groupedProductAddon']) && isset($productDetails['groupedProductOption']) && json_decode($productDetails['groupedProducts'])){
    
                    $storefrontHelper->createGroupedProduct($productId,json_decode($productDetails['groupedProducts']));
                }
            }
            // get updated list of products
            $productsCollection = $storefrontHelper->getSellerProducts($eventData);

            $formattedProductsCollection = [];
            foreach ($productsCollection['products'] as $key => $marketplaceProduct) {
                $formattedProductDetails = $dataFormatter->formatProductDetails($marketplaceProduct->get('product'));
                $formattedProductDetails['productId'] = $marketplaceProduct->get('productId');

                $formattedProductsCollection[$key] = $formattedProductDetails;
            }

            $response = [
                'status' => true,
                'total' => $productsCollection['total'],
                'products'=> $formattedProductsCollection,
                'message' => 'Product successfully created!',
            ];
        }

        $marketplaceEvent->setEventResponse($response);

        return;
    }

    public function updateMarketplaceProduct(MarketplaceEvent $marketplaceEvent)
    {
        $eventData = $marketplaceEvent->getEventData();
        
        $productMedia = $eventData['productMedia'];
        $productDetails = $eventData['productDetails'];
        
        $storefrontHelper = $this->container->get('storefront.helper');
        $dataFormatter = $this->container->get('marketplace.data.format');
        $shopwareProductHelper = $this->container->get('shopware.product.helper');
        // check Stock range in int(11)
        if($productDetails['stock'] > 2147483647 || $productDetails['price'] > 2147483647) {
            $response = ['status'=> false, 'message'=> 'Stock or Price must be less then 2147483648','code'=>400];
            $marketplaceEvent->setEventResponse($response);
                return;
        }
        // check membership restriction
        $membershiPlugin = $this->container->get('plugin.repository')->search((new Criteria())->addFilter(new EqualsFilter('name','WebkulMPMembership'))->addFilter(new EqualsFilter('active',1)), context::createDefaultContext())->first();
        
        if($membershiPlugin) {
            $membershipHelper = $this->container->get('membership.helper');
            $checkExpiry = $membershipHelper->checkMembershipStatus($eventData['customerId']);
            if($checkExpiry['status'] === false) {
                $response = ['status'=> false, 'message' => $checkExpiry['msg'], 'code'=> 400];
                $marketplaceEvent->setEventResponse($response);
                return;
            }
            if((int)$productDetails['requestType'] == 1) {
                
               $checkMembership = $membershipHelper->checkProductQuantityPrice($eventData);
            } else {

                $checkMembership = $membershipHelper->checkConditionOnUpdate($eventData);
            } 
            if($checkMembership['status'] === false) {
                $response = ['status'=> false, 'message' => $checkMembership['msg'], 'code'=> 400];
                $marketplaceEvent->setEventResponse($response);
                return;
            }
            
        }
        
        if (!empty($productMedia)) {

            $productDetails['media'] = $storefrontHelper->uploadProductMedia($productMedia, $eventData['salesChannelContext']);
            if($productDetails['media'] == false) {
                $translator = $this->container->get('translator');
                
                $response = ['status'=> false, 'message' => $translator->trans('wk-mp.product.create.imageSizeErrorMessage'), 'code'=> 400];
                $marketplaceEvent->setEventResponse($response);
                return;
            }
            
            if (!empty($productDetails['media'])) {
                $productDetails['cover'] = $productDetails['media'][0];
                unset($productDetails['media'][0]);
            } else {
                unset($productDetails['media']);
            }
        }

        // remove categories
        if (isset($productDetails['removeCategory']) && $productDetails['removeCategory']) {
            $productDetails['removeCategory'] = explode(',', $productDetails['removeCategory']);

            $shopwareProductHelper->removeProductCategory($productDetails['productId'], $productDetails['removeCategory']);
        }
        // remove media 
        if (isset($productDetails['removeMedia']) && $productDetails['removeMedia']) {
            $productDetails['removeMedia'] = explode(',', $productDetails['removeMedia']);
            $shopwareProductHelper->removeProductMedia($productDetails['productId'], $productDetails['removeMedia']);
        }
        
        // check for auto approve product
        $autoApproveProduct = $storefrontHelper->getSystemConfigurationValue('WebkulMVMarketplace.config.productAutoApprove', $eventData['salesChannelContext']);
        $productDetails['active'] = $autoApproveProduct ? true : false;

        $updatedProduct = $storefrontHelper->createShopwareProduct($productDetails, $eventData['salesChannelContext']);
        
        $response = $updatedProduct;
        if ($updatedProduct['status']) {
            // update advanced pricing
            if(isset($productDetails['pricingRules']) && $productDetails['pricingRules']) {
                $storefrontHelper->updateAdvancedPrice($productDetails,$eventData['salesChannelContext']);
            }
            
            // create grouped product
            if(isset($productDetails['groupedProductAddon'])){

                if(json_decode($productDetails['groupedProductAddon']) && json_decode($productDetails['groupedProducts'])){
                    $storefrontHelper->createGroupedProduct($productDetails['productId'],json_decode($productDetails['groupedProducts']));
                }
            }
            // get updated list of products
            $productsCollection = $storefrontHelper->getSellerProducts($eventData);
            
            $formattedProductsCollection = [];
            foreach ($productsCollection['products'] as $key => $marketplaceProduct) {
                $formattedProductDetails = $dataFormatter->formatProductDetails($marketplaceProduct->get('product'));
                $formattedProductDetails['productId'] = $marketplaceProduct->get('productId');

                $formattedProductsCollection[$key] = $formattedProductDetails;
            }

            $response = [
                'status' => true,
                'total' => $productsCollection['total'],
                'products'=> $formattedProductsCollection,
                'message' => 'Product updated successfully!',
            ];
        }

        $marketplaceEvent->setEventResponse($response);

        return;
    }

    public function updateProductVariantsList(MarketplaceEvent $marketplaceEvent)
    {
        $eventData = $marketplaceEvent->getEventData();
        $variantDetails = $eventData['variantDetails'];
      
        $storefrontHelper = $this->container->get('storefront.helper');
        $dataFormatter = $this->container->get('marketplace.data.format');
        $shopwareProductHelper = $this->container->get('shopware.product.helper');

        // first of all delete all of the existing variants
        $shopwareProductHelper->deleteProductAllVariant($eventData['productId']);


        // make an entry in product entity for each variant
        $createdVariants = $storefrontHelper->updateProductVariants($variantDetails);

        if ($createdVariants['status']) {
            // make entries into marketplace_product entity
            $marketplaceSellerId = $storefrontHelper->getSellerId($eventData['customerId']);

            foreach ($createdVariants['variantIds'] as $index => $createdVariant) {
                $productId = $createdVariant;

                $storefrontHelper->createMarketplaceProduct($productId, $marketplaceSellerId);
            }
        }

        // make an entry in product_configurator_setting entity for each group option
        $options = [];
        foreach ($variantDetails as $variant) {
            $options = array_merge($variant['options'], $options);
        }

        $productId = $variantDetails[0]['parentId'];
        $settings = [
            'productId' => $productId,
            'options' => array_unique($options),
        ];

        $storefrontHelper->updateProductConfiguratorSettings($settings);

        // get updated list variants
        $variants = $storefrontHelper->fetchProductVariantList($productId);
        $variants = $dataFormatter->formatProductVariants($variants, $variantDetails[0]['parentPrice']);

        $response = [
            'status' => true,
            'variants' => $variants,
        ];

        $marketplaceEvent->setEventResponse($response);

        return;
    }

    public function updateProductVariant(MarketplaceEvent $marketplaceEvent)
    {
        
        $eventData = $marketplaceEvent->getEventData();
        $variantDetails = $eventData['variantDetails'];

        $storefrontHelper = $this->container->get('storefront.helper');
        $storefrontHelper->updateProductVariant($variantDetails);

        $response = [
            'status' => true,
        ];

        $marketplaceEvent->setEventResponse($response);

        return;
    }

    public function deleteProductVariant(MarketplaceEvent $marketplaceEvent)
    {
        $eventData = $marketplaceEvent->getEventData();
       
        $variantId = $eventData['variantId'];

        $context = $marketplaceEvent->getContext();

        $productRepository = $this->container->get('product.repository');
        $shopwareProductHelper = $this->container->get('shopware.product.helper');    
        if ($variantId != "all") {
            $productRepository = $this->container->get('product.repository');
            $variantProduct = $productRepository->search((new Criteria())->addFilter(new EqualsFilter('id', $variantId))->addFilter(new EqualsFilter('parentId', $eventData['productId'])), $context)->first();
            $optionids = $variantProduct->getOptionIds();
            $productConfiguratorSettingRepository = $this->container->get('product_configurator_setting.repository');
           
                $criteria = (new Criteria())
                ->addFilter(new EqualsFilter('productId', $eventData['productId']))
                ->addFilter(new EqualsAnyFilter('optionId', $optionids))
                ->addAssociation('property_group_option');
               $productConfiguratorSettings = $productConfiguratorSettingRepository->search($criteria, $context)->getElements();
               foreach ($productConfiguratorSettings as $configuratorId => $configuratorDetails) {
                $shopwareProductHelper->deleteProductConfiguratorSetting($configuratorId);
            }
            $productRepository->delete([
                [
                    'id' => $variantId
                ]
            ], $context);
        } else {
           

            $shopwareProductHelper->deleteProductAllVariant($eventData['productId']);
        }

        $response = [
            'status' => true,
        ];

        $marketplaceEvent->setEventResponse($response);

        return;
    }
    public function deleteMarketplaceProduct(MarketplaceEvent $marketplaceEvent)
    {
        $eventData = $marketplaceEvent->getEventData();
        $params = $eventData['params'];
        $productId = $params['productId'];
        $storefrontHelper = $this->container->get('storefront.helper');
        $storefrontHelper->deleteShopwareProduct($productId);
      
        $response = [
            'status' => true,
            'message' => 'Product deleted successfully!',
        ];
        $marketplaceEvent->setEventResponse($response);

        return;
    }
}

?>
