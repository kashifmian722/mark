<?php

namespace Webkul\MultiVendor\EventSubscriber;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Webkul\MultiVendor\Event\MarketplaceEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Webkul\MultiVendor\EventDispatcher\MarketplaceEventDispatcher;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class ProfileSubscriber implements EventSubscriberInterface
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

    public function getStoreProfile(MarketplaceEvent $marketplaceEvent)
    {
       
        $eventData = $marketplaceEvent->getEventData();
        
        $customerId = $eventData['customerId'];
        $params = null;
        if(isset($eventData['params'])){

            $params = $eventData['params'];
        }
       
        $currency = null;
        if(isset($eventData['salesChannelContext'])){

            $currency = $eventData['salesChannelContext']->getCurrency();
        }
       
        $profieHelper = $this->container->get('profile.helper');
        $dataFormatter = $this->container->get('marketplace.data.format');
        $marketplaceSellerRepository = $this->container->get('marketplace_seller.repository');

        // get marketplace seller
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('customerId', $customerId))
            ->addAssociation('customer')
            ->addAssociation('mediaLogo')
            ->addAssociation('mediaOwner')
            ->addAssociation('mediaBanner');

        try {
            $marketplaceSeller = $marketplaceSellerRepository->search($criteria, $marketplaceEvent->getContext())
                ->first();
        } catch (\Exception $exception) {
            $criteria = (new Criteria())
                ->addFilter(new EqualsFilter('marketplace_seller.storeSlug', $customerId))
                ->addAssociation('customer')
                ->addAssociation('mediaLogo')
            ->addAssociation('mediaOwner')
            ->addAssociation('mediaBanner');

            $marketplaceSeller = $marketplaceSellerRepository->search($criteria, $marketplaceEvent->getContext())
            ->first();
        }
       
        $profile = [];
        if ($marketplaceSeller && $marketplaceSeller->get('isApproved') && $marketplaceSeller->get('profileStatus')) {
            $customerId = $marketplaceSeller->get('customerId');
  
            $profile = [
                'id' => $marketplaceSeller->get('customerId'),
                'email' => $marketplaceSeller->get('customer')->getEmail(),
                'storeSlug' => $marketplaceSeller->get('storeSlug'),
                'storeOwner' => $marketplaceSeller->get('storeOwner'),
                'storeTitle' => $marketplaceSeller->get('storeTitle'),
                'storeDescription' => html_entity_decode(htmlspecialchars_decode($marketplaceSeller->get('storeDescription'))),
                'socialSites' => $marketplaceSeller->get('socialSites') ? $marketplaceSeller->get('socialSites') : [],
                'logoUrl'=> $marketplaceSeller['mediaLogo']? $marketplaceSeller['mediaLogo']->getUrl(): null,
                'bannerUrl'=> $marketplaceSeller['mediaBanner']? $marketplaceSeller['mediaBanner']->getUrl(): null,
                'ownerUrl'=> $marketplaceSeller['mediaOwner']? $marketplaceSeller['mediaOwner']->getUrl(): null
            ];
            
            $reviewsCollection = $profieHelper->getStoreReviews($customerId);
            
            $formattedReviewsCollection = $dataFormatter->formatStoreReviewsDetails($reviewsCollection);
            [$sellerProducts,$totalProducts] =  $this->getSellerActiveProducts($customerId,$params,$eventData['salesChannelContext']);
            $response = [
                'status' => true,
                'profile' => $profile,
                'sellerReviews' => $formattedReviewsCollection,
                'sellerProducts' => $sellerProducts,
                'totalProducts' => $totalProducts,
                'currency' => $currency
            ];
            
        } else {
            $response = [
                'status' => false,
                'profile' => [],
            ];
        }
        
        $marketplaceEvent->setEventResponse($response);

        return;
    }

    public function updateSellerProfile(MarketplaceEvent $marketplaceEvent)
    {
        $eventData = $marketplaceEvent->getEventData();
        
        $attachments = $eventData['attachments'];
        $customerId = $eventData['customerId'];
        
        $profileHelper = $this->container->get('profile.helper');
        $storefrontHelper = $this->container->get('storefront.helper');
        $marketplaceSellerRepository = $this->container->get('marketplace_seller.repository');

        $isSlugVerified = $profileHelper->verifySlugAvailability($eventData['storeDetails']['slug'], $customerId);

        if ($isSlugVerified) {
            $targetDirectory = $this->container->get('kernel')->getProjectDir() . '/public/mp-media/public-profile/' . $eventData['customerId'] . '/';

            $sellerId = $storefrontHelper->getSellerId($customerId);
            $profileData = [
                "id" => $sellerId,
                "storeSlug" => $eventData['storeDetails']['slug'],
                "storeTitle" => $eventData['storeDetails']['title'],
                "storeOwner" => $eventData['storeDetails']['owner'],
                "storeDescription" => htmlentities(htmlspecialchars($eventData['storeDetails']['description']))
            ];
           
            if (isset($eventData['storeDetails']['socialSite'])) {
                $profileData['socialSites'] = $eventData['storeDetails']['socialSite'];
            }

            // create directory if do not exist
            if (!is_dir($targetDirectory)) {
                mkdir($targetDirectory, 0777, true);
            }
            
            // upload banner
            if (isset($attachments['storeBanner']) && $storefrontHelper->verifyImage($attachments['storeBanner'])) {
                $bannerMediaId = $storefrontHelper->uploadSellerProfileMedia($attachments['storeBanner'], $eventData['salesChannelContext']);
                $profileData['storeBannerId'] = $bannerMediaId;
                $targetFile =  'banner.png';
                move_uploaded_file($attachments['storeBanner']->getRealPath(), $targetDirectory . $targetFile);
            }

            // upload shop logo
            if (isset($attachments['storeLogo']) && $storefrontHelper->verifyImage($attachments['storeLogo'])) {
                $logoMediaId = $storefrontHelper->uploadSellerProfileMedia($attachments['storeLogo'], $eventData['salesChannelContext']);
                $profileData['storeLogoId'] = $logoMediaId;
                $targetFile =  'logo.png';
                move_uploaded_file($attachments['storeLogo']->getRealPath(), $targetDirectory . $targetFile);
            }

            // upload shop owner profile
            if (isset($attachments['storeOwner']) && $storefrontHelper->verifyImage($attachments['storeOwner'])) {
                $ownerMediaId = $storefrontHelper->uploadSellerProfileMedia($attachments['storeOwner'], $eventData['salesChannelContext']);
                $profileData['storeOwnerId'] = $ownerMediaId;
                $targetFile =  'owner.png';
                move_uploaded_file($attachments['storeOwner']->getRealPath(), $targetDirectory . $targetFile);
            }
            
            // update store details in db
            $marketplaceSellerRepository->upsert([$profileData], $marketplaceEvent->getContext());

            $response = [
                'status' => true,
                'message' => 'Store profile successfully updated!'
            ];
        } else {
            $response = [
                'status' => false,
                'message' => 'Slug is not available. Please enter another slug for your store.'
            ];
        }

        // set event response
        $marketplaceEvent->setEventResponse($response);

        return;
    }

    public function submitStoreReview(MarketplaceEvent $marketplaceEvent)
    {
        $profileHelper = $this->container->get('profile.helper');
        $dataFormatter = $this->container->get('marketplace.data.format');

        $eventData = $marketplaceEvent->getEventData();
        $review = $eventData['review'];
        $review['reviewerId'] = $eventData['reviewerId'];
        $review['sellerCustomerId'] = $eventData['storeOwnerId'];

        $profileHelper->createStoreReview($review);
        $reviewsCollection = $profileHelper->getStoreReviews($review['sellerCustomerId']);
        $formattedReviewsCollection = $dataFormatter->formatStoreReviewsDetails($reviewsCollection);

        // set event response
        $marketplaceEvent->setEventResponse([
            'status' => true,
            'reviewsCollection' => $formattedReviewsCollection
        ]);

        return;
    }

    private function getSellerActiveProducts($customerId, $params, $salesChannelContext)
    {
        $mpProductRepository = $this->container->get('marketplace_product.repository');
        $criteria = new Criteria();
        $limit = (int)12;
        if (isset($params['p']) && $params['p']) {
            $page = $params['p'];
        }
        $criteria->addFilter(new EqualsFilter('marketplace_product.marketplace_seller.customer.id', $customerId))->addFilter(new EqualsFilter('marketplace_product.product.active',1))->addFilter(new EqualsFilter('marketplace_product.product.parentId', null))->setLimit($limit)->addFilter(new ProductAvailableFilter($salesChannelContext->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_LINK));
        if(isset($page) && $page!= '') {
            $criteria = $criteria->setLimit($limit)
                ->setOffset(($page - 1) * $limit);
        }
        $mpProducts = $mpProductRepository->search($criteria,$salesChannelContext->getContext())->getElements();
        $totalCriteria = new Criteria();
        $totalCriteria->addFilter(new EqualsFilter('marketplace_product.marketplace_seller.customer.id', $customerId))->addFilter(new EqualsFilter('marketplace_product.product.active',1))->addFilter(new EqualsFilter('marketplace_product.product.parentId', null))->addFilter(new ProductAvailableFilter($salesChannelContext->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_LINK));
        $totalProducts = $mpProductRepository->search($totalCriteria,$salesChannelContext->getContext())->getTotal();
        
        $products = [];
        if($mpProducts) {

            $storeProfileHelper = $this->container->get('profile.helper');
            $products = $storeProfileHelper->getProductList($mpProducts,$salesChannelContext);
        }
        
        return [$products,$totalProducts];
        
    }
}

?>
