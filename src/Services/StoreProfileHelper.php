<?php

namespace Webkul\MultiVendor\Services;

use Shopware\Core\Content\Product\SalesChannel\ProductListRoute;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class StoreProfileHelper
{
    private $container;
    private $productListRoute;

    public function __construct(
        ContainerInterface $container,
        ProductListRoute $productListRoute
    ) {
        $this->container = $container;
        $this->productListRoute = $productListRoute;
    }

    public function verifySlugAvailability($slug, $customerId = null)
    {
        $mpSellerRepository = $this->container->get('marketplace_seller.repository');

        $critera = (new Criteria())
            ->addFilter(new EqualsFilter('marketplace_seller.storeSlug', $slug));

        $existingSlugSeller = $mpSellerRepository->search($critera, Context::createDefaultContext());

        $result = true;
        if ($existingSlugSeller->first()) {
            if ($existingSlugSeller->first()->get('customerId') !== $customerId)
            $result = false;
        }

        return $result;
    }

    public function createStoreReview($review)
    {
        $storefrontHelper = $this->container->get('storefront.helper');
        $mpReviewRepository = $this->container->get('marketplace_review.repository');

        // create review
        $storeOwnerId = $storefrontHelper->getSellerId($review['sellerCustomerId']);
        $mpReviewRepository->create([
            [
                'reviewTitle' => $review['reviewTitle'],
                'reviewRating' => $review['starRating'],
                'customerId' => $review['reviewerId'],
                'marketplaceSellerId' => $storeOwnerId,
                'reviewDescription' => $review['reviewDescription'],
            ]
        ], Context::createDefaultContext());

        return;
    }

    public function getStoreReviews($customerId)
    {
        $mpReviewRepository = $this->container->get('marketplace_review.repository');

        // @TODO:- filter out only specific data
        $criteria = (new Criteria())
            ->addAssociation('customer')
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        try {
            $criteria->addFilter(new EqualsFilter('marketplace_review.marketplace_seller.customerId', $customerId));

            $reviewsCollection = $mpReviewRepository->search($criteria, Context::createDefaultContext());

        } catch (\Exception $exception) {
            $criteria->resetFilters()
                ->addFilter(new EqualsFilter('marketplace_review.marketplace_seller.storeSlug', $customerId));

            $reviewsCollection = $mpReviewRepository->search($criteria, Context::createDefaultContext());
        }

        $reviewsCollection = $reviewsCollection
            ->getEntities()
            ->getElements();

        return $reviewsCollection;
    }
    public function getProductList($mpProducts, $salesChannelContext)
    {
        foreach($mpProducts as $mpProduct) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $mpProduct->get('productId')))->addAssociations(['options','options.group','cover']);
            $products[] = $this->productListRoute->load($criteria,$salesChannelContext)->getObject()->first();
            
        }
        return $products;
    }
}
