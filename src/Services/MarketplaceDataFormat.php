<?php

namespace Webkul\MultiVendor\Services;

use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class MarketplaceDataFormat
{
    private $container;

    public function __construct(
        ContainerInterface $container
    ) {
        $this->container = $container;
    }

    public function formatOrderLineItem($orderLineItem)
    {
        $coverUrl = $orderLineItem->get('cover') ? $orderLineItem->get('cover')->getUrl() : null;

        $formattedOrderLineItem = [];

        // get variant details
        if ($productGroupOptions = $orderLineItem->get('payload')['options']) {
            $formattedOrderLineItem['variants'] = [];

            foreach ($productGroupOptions as $option) {
                array_push($formattedOrderLineItem['variants'], [
                    'option' => $option['option'],
                    'group' => $option['group'],
                ]);
            }
        }

        $formattedOrderLineItem += [
            'cover' => $coverUrl,
            'name' => $orderLineItem->get('label'),
            'sum' => $orderLineItem->get('price')->getTotalPrice(),
            'price' => $orderLineItem->get('price')->getUnitPrice(),
            'quantity' => $orderLineItem->get('price')->getQuantity(),
        ];

        return $formattedOrderLineItem;
    }

    public function formatProductDetails($productDetails)
    {
        
        $formattedProduct = [
            'id' => $productDetails->get('id'),
            'name' => $productDetails->get('name'),
            'cover' => $productDetails->get('cover'),
            'media' => $productDetails->get('media'),
            'stock' => $productDetails->get('availableStock'),
            'active' => $productDetails->get('active'),
            'description' => html_entity_decode(htmlspecialchars_decode($productDetails->get('description'))),
            'productNumber' => $productDetails->get('productNumber'),
            'minPurchase' => $productDetails->get('minPurchase'),
            'maxPurchase' => $productDetails->get('maxPurchase')
            
        ];
        if($productDetails->get('prices')->getElements()){
            $elements = $productDetails->get('prices')->getElements();
            
            usort($elements, function($a, $b) {
                return $a->getQuantityStart() <=> $b->getQuantityStart();
            });
            
            foreach($elements as $element) {
                $arr[] = $element;
            }
            
            foreach($arr as $k => $v) {
                $advancedPrices[$v->getRuleId()][]=$v;
            }
            
            $pricingRules = [];
            foreach($advancedPrices as $advancedPrice){
                $price = [];
                foreach($advancedPrice as $key=>$advanced) {
                  $priceObj = $advanced->getPrice()->first();
                  $price[] = ['currencyId'=>$priceObj->getCurrencyId(),'net'=>$priceObj->getNet(),'gross'=>$priceObj->getGross()];
                   $prices[] = ['productId'=>$productDetails->get('id'),'quantityStart'=>$advanced->get('quantityStart'), 'quantityEnd'=>$advanced->get('quantityEnd'), 'price'=> $price];
                    $rule = ['prices'=>$prices, 'rule'=>$advanced->getRule()->getName(), 'ruleId'=>$advanced->getRuleId()];
                    $price = [];
                }
                $prices = [];
                array_push($pricingRules,$rule);
            }
            $formattedProduct['advancedPrices'] = $pricingRules;
            
        }
        
        if($productDetails->get('price')){  
            $formattedProduct['price'] = $productDetails->get('price')->getElements()[array_keys($productDetails->get('price')->getElements())[0]]->getGross();
            $formattedProduct['currencyId'] = $productDetails->get('price')->getElements()[array_keys($productDetails->get('price')->getElements())[0]]->getCurrencyId();
            $formattedProduct['currency'] = $this->container->get('currency.repository')->search((new Criteria())->addFilter(new EqualsFilter('id',$formattedProduct['currencyId'])),Context::createDefaultContext())->first();
            

        }
        if($productDetails->get('tax')){
            $formattedProduct['tax'] = $productDetails->get('tax')->getUniqueIdentifier();
            $formattedProduct['taxEntity'] = $productDetails->get('tax');
        }
        if(!empty($productDetails->get('manufacturer'))){
            $formattedProduct['manufacturer'] = $productDetails->get('manufacturer')->getId();
            $formattedProduct['manufacturerName'] = $productDetails->get('manufacturer')->getName();
        }
        
        $categoriesCollection = [];
        if(!empty($productDetails->get('categories'))){

            foreach ($productDetails->get('categories')->getElements() as $categoryId => $category) {
                $categoryDetails = [
                    'id' => $categoryId,
                    'name' => $category->getName()
                ];
    
                array_push($categoriesCollection, $categoryDetails);
            }
        }

        $formattedProduct['categories'] = $categoriesCollection;

        return $formattedProduct;
    }
    public function in_array_recursive( $val, $arr) {
        foreach( $arr as $v ) {
            
            foreach($v as $m) {
                if( in_array($val, $m ) )
                    return TRUE;      
            }
        }
        return FALSE;
    }

    public function formatProductVariants($variants, $productPrice)
    {
        $formattedVariantCollection = [];
        $propertyGroupOption = $this->container->get('property_group_option.repository');

        foreach ($variants as $variant) {
            $name = $variant->getName();

            if (!$name) {
                $optionIds = $variant->getOptionIds();

                $name = '';
                foreach ($optionIds as $optionId) {
                    $criteria = (new Criteria())
                    ->addFilter(new EqualsFilter('id', $optionId));

                    if ($name)
                        $name .= ' - ';

                    $name .= $propertyGroupOption->search($criteria, Context::createDefaultContext())->first()->getName();
                }
            }


            $price = $variant->getPrice() ?? $productPrice;

            if (getType($price) == "object") {
                $prices = $variant->getPrice()->getElements();
                $price = $prices[array_keys($prices)[0]]->getGross();
            }
            
            $formattedVariant = [
                'name' => $name,
                'id' => $variant->get('id'),
                'price' => $price,
                'stock' => $variant->getStock(),
                'active' => 1
            ];

            $formattedVariantCollection[] = $formattedVariant;
        }

        return $formattedVariantCollection;
    }

    public function formatStoreReviewsDetails($reviewsCollection)
    {
        $formattedReviewCollection = [];
        foreach ($reviewsCollection as $review) {
            $formattedReview = [
                'reviewTitle' => $review->get('reviewTitle'),
                'reviewRating' => $review->get('reviewRating'),
                'reviewDescription' => $review->get('reviewDescription'),
                'createdAt' => $review->get('createdAt')->format('d-M-Y'),
                'customerName' => $review['customer']->getFirstName() . ' ' . $review['customer']->getLastName()
            ];

            array_push($formattedReviewCollection, $formattedReview);
        }

        return $formattedReviewCollection;
    }

    public function formatPropertyGroup($propertyGroup)
    {
        return [
            'id' => $propertyGroup->getId(),
            'name' => $propertyGroup->get('name'),
        ];
    }
}
