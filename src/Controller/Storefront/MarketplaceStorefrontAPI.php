<?php

namespace Webkul\MultiVendor\Controller\Storefront;

use Exception;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Core\Content\Category\SalesChannel\CategoryListRoute;

/**
 * @RouteScope(scopes={"storefront"})
 */
class MarketplaceStorefrontAPI extends StorefrontController
{
    const OK_RESPONSE_CODE = 200;
    const CREATED_RESPONSE_CODE = 202;
    const CONFLICT_RESPONSE_CODE = 409;
    
    private $containerInterface;
    private $sellerRepository;
    private $marketplaceProductRepository;
    private $categoryListRoute;


    public function __construct(
        ContainerInterface $containerInterface,
        EntityRepository $sellerRepository,
        EntityRepository $marketplaceProductRepository,
        CategoryListRoute $categoryListRoute
    ) {
        $this->container = $containerInterface;
        $this->sellerRepository = $sellerRepository;
        $this->marketplaceProductRepository = $marketplaceProductRepository;
        $this->categoryListRoute = $categoryListRoute;
    }

    /**
     * @Route("/storefront-api/v1/marketplace/config", name="frontend.marketplace.api.fetch.configuration", methods={"GET"})
    */
    public function fetchMarketplaceConfiguration(Request $request): JsonResponse
    {
        $sellerRepository = $this->sellerRepository;
        $customerId = $request->query->get('customerId');

        // get customer from marketplace_seller entity
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('marketplace_seller.customer.id', $customerId));

        $marketplaceSeller = $sellerRepository->search($criteria, Context::createDefaultContext());

        $isSellerApplied = $isSellerApproved = 0;
        if ($marketplaceSeller && $marketplaceSeller->getIds()) {
            $isSellerApplied = $marketplaceSeller->first()->get('isApplied');
            $isSellerApproved = $marketplaceSeller->first()->get('isApproved');
        }
        return new JsonResponse([
            'status' => true,
            'result' => [
                'isSellerApplied' => (bool) $isSellerApplied,
                'isSellerApproved' => (bool) $isSellerApproved
            ]
        ], self::OK_RESPONSE_CODE);
    }

    /**
     * this function will make entry in marketplace_sellers with the status if customer want to be a seller
     *
     * @Route("/storefront-api/v1/marketplace/config", name="frontend.marketplace.api.store.configuration", methods={"POST"}, defaults={"csrf_protected"=false, "XmlHttpRequest"=true})
    */
    public function storeMarketplaceConfiguration(Request $request, SalesChannelContext $context): JsonResponse
    {
        $sellerRepository = $this->sellerRepository;
        $customerId = $request->request->get('customerId');
        $storefrontHelper = $this->container->get('storefront.helper');
        $sellershipStatus = $request->request->get('sellershipStatus');

        // get customer from marketplace_seller entity
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('marketplace_seller.customer.id', $customerId));
        
        $marketplaceSeller = $sellerRepository->searchIds($criteria, Context::createDefaultContext());
        
            
        // interact with database
        if (!($marketplaceSeller && $marketplaceSeller->getIds())) {
            $responseStatus = true;
            $reponseCode = self::CREATED_RESPONSE_CODE;

            $response = $sellerRepository->create([[
                "isApplied" => true,
                "isApproved" => $storefrontHelper->getSystemConfigurationValue('WebkulMVMarketplace.config.sellerAutoApprove', $context),
                "profileStatus"=> $storefrontHelper->getSystemConfigurationValue('WebkulMVMarketplace.config.profileAutoApprove', $context),
                "customer" => [
                    "id" => $customerId
                ]
            ]], Context::createDefaultContext());
            // sending mail to admin and customer both
            $mpMailHelper = $this->container->get('mpmail.helper');
            try{

                $mpMailHelper->applySellerShipMailToAdmin($customerId, $context->getSalesChannelId());
            } catch(Exception $ex) {
                
            }

        } else {
            $responseStatus = false;
            $reponseCode = self::CONFLICT_RESPONSE_CODE;
        }

        return new JsonResponse([
            'status' => $responseStatus,
        ], $reponseCode);
    }

    /**
     * @Route(
        * "/frontend/marketplace/meta-product-data/",
        * name="frontend.marketplace.meta_product_data", methods={"GET"},
        * defaults={"productId": null},
     * )
    */
    public function fetchProductMetaData(SalesChannelContext $salesChannelContext, Request $request): JsonResponse
    {
        $productId = $request->query->get('productId');
        $criteria = new Criteria();
        $context = Context::createDefaultContext();
        $storefrontHelper = $this->container->get('storefront.helper');
        $dataFormatter = $this->container->get('marketplace.data.format');

        // collect manufacturers
        $productManufacturerRepository = $this->container->get('product_manufacturer.repository');
        $manufacturerCollection = $productManufacturerRepository->search($criteria, $context);

        // collect categories
        
        $categoryCriteria = new Criteria();
        $categoryCriteria->addFilter(new EqualsFilter('active',1))->addFilter(new EqualsFilter('parentId', null));
        $categoryCollection = $this->categoryListRoute->load($categoryCriteria, $salesChannelContext)->getObject();

        // collect taxes
        $taxRepository = $this->container->get('tax.repository');
        $taxCollection = $taxRepository->search($criteria, $context);

        // collect product groups properties
        $propertyGroupRepository = $this->container->get('property_group.repository');
        $propertyGroups = $propertyGroupRepository->search($criteria, $context)
            ->getEntities()
            ->getElements();

        $formattedPropertyGroups = [];
        foreach ($propertyGroups as $group) {
            array_push($formattedPropertyGroups, $dataFormatter->formatPropertyGroup($group));
        }

        // collect group opted options
        if ($productId) {
            $optedOptions = $storefrontHelper->getSelectedGroupOptions($productId);

            foreach ($optedOptions as $groupId => $options) {
                foreach ($formattedPropertyGroups as $index => $group) {

                    if ($group['id'] == $groupId) {
                        $formattedPropertyGroups[$index]['selectedGroupOptions'] = $options;
                    }
                }
            }
        }
        // collect rule collection
        $ruleCollection = $this->container->get('rule.repository')->search((new Criteria()),$context)->getElements();

        $preparedCollection = [
            'taxes' => $taxCollection,
            'categories' => $categoryCollection,
            'manufacturers' => $manufacturerCollection,
            'propertyGroups' => $formattedPropertyGroups,
            'ruleCollection' => $ruleCollection
        ];

        return new JsonResponse([
            'status' => true,
            'response' => $preparedCollection
        ], self::OK_RESPONSE_CODE);
    }

    /**
     * @Route("/frontend/category/tree/", name="frontend.marketplace.api.category.tree", methods={"GET"})
    */
    public function getSubCategoryList(SalesChannelContext $salesChannelContext, Request $request)
    {
        $parentId = $request->query->get('treeParentId');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active',1))->addFilter(new EqualsFilter('parentId', $parentId));
        $categoryCollection = $this->categoryListRoute->load($criteria, $salesChannelContext)->getObject();
        return new JsonResponse([
            'status' => true,
            'categories' => $categoryCollection
        ], self::OK_RESPONSE_CODE);
    }

    /**
     * @Route("/storefront-api/variant/tree/{groupId}", name="frontend.marketplace.api.variant.tree", methods={"GET"})
    */
    public function getSubVariantList($groupId)
    {
        $propertyGroupOptionRepository = $this->container->get('property_group_option.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('groupId', $groupId));

        $groupOptions = $propertyGroupOptionRepository->search($criteria, Context::createDefaultContext());

        return new JsonResponse([
            'status' => true,
            'groupOptions' => $groupOptions,
        ], self::OK_RESPONSE_CODE);
    }

    /**
     * @Route("/storefront-api/verify-slug/{slug}", name="frontend.marketplace.slug.verification", methods={"GET"})
    */
    public function slugVerification($slug)
    {
        $profileHelper = $this->container->get('profile.helper');

        return new JsonResponse([
            'status' => $profileHelper->verifySlugAvailability($slug),
        ], self::OK_RESPONSE_CODE);
    }
}
