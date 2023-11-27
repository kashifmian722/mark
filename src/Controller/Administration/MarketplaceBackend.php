<?php declare(strict_types=1);

namespace Webkul\MultiVendor\Controller\Administration;

use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @RouteScope(scopes={"api"})
 */
class MarketplaceBackend extends StorefrontController
{
    /**
     * @var ContainerInterface
     */
    protected $container;
    protected $systemConfigService;

    public function __construct(ContainerInterface $container, SystemConfigService $systemConfigService)
    {
        $this->container = $container;
        $this->systemConfigService = $systemConfigService;
    }
    /**
     * @Route("/api/wk.marketplace/seller/approve", name="wk.marketplace.seller.approve", methods={"POST"})
     */
    public function approveSeller(Request $request)
    {
       
       $sellerRepository = $this->container->get('marketplace_seller.repository');
       foreach($request->get('sellerId') as $id){
       $sellerRepository->upsert([['id'=>$id, 'isApproved'=>$request->get('active')]], Context::createDefaultContext());
       
       }
       return new JsonResponse(true);
        
    }
     /**
     * @Route("/api/wk.marketplace/product/status", name="wk.marketplace.product.status", methods={"POST"})
     */
    public function saveProductStatus(Request $request)
    {
       
       $sellerRepository = $this->container->get('product.repository');
       foreach($request->get('productId') as $id){
       $sellerRepository->upsert([['id'=>$id, 'active'=>$request->get('active')]], Context::createDefaultContext());
       }
       return new JsonResponse(true);
        
    }
    /**
     * @Route("api/wk.marketplace/remove/seller/products", name="wk.marketplace.remove.seller.products", methods={"POST"})
     */
    public function removeSellerProducts(Request $request)
    {
        $sellerId = $request->request->get('sellerId');
        $markeplaceProductRepo = $this->container->get('marketplace_product.repository');
        $criteria = (new Criteria())->addFilter(new EqualsFilter('marketplace_product.marketplace_seller.id', $sellerId))->addAssociations(['marketplace_seller', 'product']);
        $products = $markeplaceProductRepo->search($criteria, Context::createDefaultContext());
       $productIds = [];
        foreach($products as $product) {
            if ($product->get('product')) {
                $productIds[] = $product->get('product')->get('id');
            }
        }
       
        if ($productIds) {
            $storefrontHelper = $this->container->get('storefront.helper');
            $storefrontHelper->deleteShopwareProduct($productIds);
        }
        
        return new JsonResponse(true);
    }
      /**
     * @Route("api/wk.marketplace/delete/shopware/products", name="wk.marketplace.delete.shopware.products", methods={"POST"})
     */
    public function deleteShopwareProducts(Request $request)
    {
        $productId = $request->request->get('productId');
        $markeplaceProductRepo = $this->container->get('marketplace_product.repository');
        $criteria = (new Criteria())->addFilter(new EqualsFilter('id', $productId));
        $marketplaceProduct = $markeplaceProductRepo->search($criteria, Context::createDefaultContext())->first();
        $swProductId = $marketplaceProduct['productId'];
        $orderlineItem = $this->container->get('order_line_item.repository')->search((new Criteria())->addFilter(new EqualsFilter('productId',$swProductId)), Context::createDefaultContext())->getElements();
      
        if ($orderlineItem) {
            $this->container->get('product.repository')->upsert([['id'=>$swProductId, 'active'=>false]], Context::createDefaultContext());
        } else {
            $this->container->get('product.repository')->delete([['id'=>$swProductId]], Context::createDefaultContext());
            $markeplaceProductRepo->delete([['id'=>$productId]], Context::createDefaultContext());
        }
        
        return new JsonResponse(true);
    }
     /**
     * @Route("api/wk.marketplace/bulk/delete/shopware/products", name="wk.marketplace.bulk.delete.shopware.products", methods={"POST"})
     */
    public function bulkDeleteShopwareProducts(Request $request)
    {
        $productIds = $request->request->get('productIds');
        
        $storefrontHelper = $this->container->get('storefront.helper');
        $response = $storefrontHelper->deleteShopwareProduct($productIds);
        return new JsonResponse($response);
    }
     /**
     * @Route("/api/wk.marketplace/transaction", name="wk.marketplace.transaction", methods={"POST"})
     */
    public function saveTransaction(Request $request)
    {
        $commissionIds = $request->request->get('commissionId');   
        $repository = $this->container->get('marketplace_commission.repository');
        $transactionId = Uuid::randomHex();
        foreach($commissionIds as $id){
            $repository->upsert([[
                'id' => $id,
                'transactionId' => $transactionId,
                'isPaid' => true,
                'transactionComment' => $request->request->get('message')
            ]], Context::createDefaultContext());
        }
        return new JsonResponse(true);  
        
    }
    /**
     * @Route("/api/wk.marketplace/update/order", name="wk.marketplace.update.order", methods={"POST"})
     
     */
    public function updateOrderStatus(Request $request){
       $mpOrderId = $request->request->get('mpOrderId');
       $stateId = $request->request->get('stateId');
       $data = ['id'=>$mpOrderId, 'orderStatus'=>$stateId];
       $this->container->get('marketplace_order.repository')->upsert([$data], Context::createDefaultContext());
       return new JsonResponse(true); 
    }
    /**
     * 
     * @Route("/api/wk.marketplace/save/config", name="wk.marketplace.save.config", methods={"POST"})
     */
    public function saveConfig(Request $request) 
    {
        $configValues = $request->request->get('config');
        $saleschannelId = $request->request->get('saleschannelId');
       
        if ($configValues) {
            foreach ($configValues as $key => $configValue) {
                $this->systemConfigService->set('WebkulMVMarketplace.config.' . $key, $configValue, $saleschannelId);
            }
            
        }
        return new JsonResponse(true);
    }
    /**
     * @Route("/api/wk.marketplace/create/shipping/label", name="wk.marketplace.shipping.label", methods={"POST"})
     */
    public function createShippingLabel(Request $request)
    {
        $orderNumber = $request->request->get('orderNumber');
        $productId = $request->request->get('productId');
        $quantity = $request->request->get('quantity');
        $sellerId = $request->request->get('sellerId');
        $productPrice = $request->request->get('productPrice');
        $seller = $this->container->get('marketplace_seller.repository')->search((new Criteria())->addFilter(new EqualsFilter('id',$sellerId))->addAssociation('customer'),Context::createDefaultContext())->first();
        
        $json = array();
        if ($this->systemConfigService->get('WebkulMPDhlShipping.config.sandboxMode')) {
            $url = 'https://xmlpitest-ea.dhl.com/XMLShippingServlet';
        } else {
            $url = 'https://xmlpi-ea.dhl.com/XMLShippingServlet';
        }
        $orderInfo = $this->container->get('order.repository')->search((new Criteria())->addFilter(new EqualsFilter('orderNumber',$orderNumber))->addAssociation('billingAddress')->addAssociation('billingAddress.country')->addAssociation('currency')->addAssociation('lineItems'),Context::createDefaultContext())->first();
        
        
        $sellerInfo = $this->container->get('marketplace_seller.repository')->search((new Criteria())->addFilter(new EqualsFilter('customerId',$seller->get('customer')->getId())),Context::createDefaultContext())->first();
        $sellerDhlInfo = $this->container->get('marketplace_seller_dhl_shipping.repository')->search((new Criteria())->addFilter(new EqualsFilter('sellerId',$sellerInfo->getId())),Context::createDefaultContext());
        
        if($sellerDhlInfo->getTotal()>0) {
            $sellerDhlInfo = $sellerDhlInfo->first();
            // if ($this->systemConfigService->get('WebkulMPDhlShipping.config.allowCompanyLogo')) {
            //   $sellerDhlInfo['logoMediaId'] = $sellerInfo->get('storeLogoId');
            // }
            // if (!$sellerDhlInfo['logoMediaId'] && $this->systemConfigService->get('WebkulMPDhlShipping.config.allowCompanyLogo')) {
            //   $json['error'] = 'Company Logo not found';
            // }
            $sellerDhlInfo['name'] = $seller->get('customer')->getFirstName().' '.$seller->get('customer')->getLastName();
            
        } else {
            $countryId = $this->systemConfigService->get('WebkulMPDhlShipping.config.country');
            if ($countryId) {
                $country = $this->container->get('country.repository')->search((new Criteria())->addFilter(new EqualsFilter('id',$countryId)),Context::createDefaultContext());
                if ($country) {
                  $countryCode = $country->first()->getIso();
                  $countryName = $country->first()->getName();
                }
              }
            $sellerDhlInfo = array(
                'name' => $this->systemConfigService->get('WebkulMPDhlShipping.config.title'),
                'accessId' => $this->systemConfigService->get('WebkulMPDhlShipping.config.accessId'),
                'password' => $this->systemConfigService->get('WebkulMPDhlShipping.config.password'),
                'accountNumber'=> $this->systemConfigService->get('WebkulMPDhlShipping.config.accountNumber'),
                'city'=> $this->systemConfigService->get('WebkulMPDhlShipping.config.city'),
                'postCode'=> $this->systemConfigService->get('WebkulMPDhlShipping.config.zipCode'),
                'countryCode'=> $countryCode,
                'countryName'=> $countryName,
                'weightUnit'=> $this->systemConfigService->get('WebkulMPDhlShipping.config.weightUnit'),
                'sizeUnit' => $this->systemConfigService->get('WebkulMPDhlShipping.config.size')
            );
            // if ($this->systemConfigService->get('WebkulMPDhlShipping.config.customLogo')) {
            //   $sellerDhlInfo['logoMediaId'] = $this->systemConfigService->get('WebkulMPDhlShipping.config.logoMedia');
            // }
            // if(!$sellerDhlInfo['logoMediaId'] && $this->systemConfigService->get('WebkulMPDhlShipping.config.customLogo')) {
            //   $json['error'] = 'Company Logo not found';
            // }
        }
        
        if ($sellerDhlInfo && !isset($json['error'])) {
            // if ($this->systemConfigService->get('WebkulMPDhlShipping.config.customLogo') && $sellerDhlInfo['logoMediaId']) {
            //     $media = $this->container->get('media.repository')->search((new Criteria())->addFilter(new EqualsFilter('id',$sellerDhlInfo['logoMediaId'])),Context::createDefaultContext())->first();
            //     if ($media) {

            //       $sellerDhlInfo['logo'] = $media->getUrl();
            //       $extension = $media->getFileExtension();
            //       $img_array = array(
            //         'jpg',
            //         'jpeg',
            //         'gif',
            //         'png'
            //       );
            //       if (!isset($extension) || !in_array($extension, $img_array))
            //       $json['error'] = 'Invalid logo extension';
            //     } else {
            //       $json['error'] = 'Seller Company Logo Not Found';
            //     }
            // }
            $productInfo = $this->container->get('product.repository')->search((new Criteria())->addFilter(new EqualsFilter('id',$productId)),Context::createDefaultContext())->first();
            $productId = $productInfo->getId();
            if($productInfo->getParentId()){
              $productInfo = $this->container->get('product.repository')->search((new Criteria())->addFilter(new EqualsFilter('id',$productInfo->getParentId())),Context::createDefaultContext())->first();
            }
            if ($productInfo->getWeight() < 0.1) {
              $json['error'] = $this->trans('dhl-shipping.productWeightWarning');
            }
            if ($productInfo->getHeight() < 0.1 || $productInfo->getWidth() < 0.1 || $productInfo->getLength() < 0.1) {
              $json['error'] = $this->trans('dhl-shipping.productDimensionWarning');
            }
      
            $productGrossPrice = number_format((float)$productPrice, 2, '.', '');
            $ProductWeight = $quantity * $productInfo->getWeight();
            $ProductWeight = number_format((float)$ProductWeight, 1, '.', '');

            if($productInfo && !(isset($json['error']))) {
                if (strtolower($sellerDhlInfo['sizeUnit']) == 'in')
                $sellerDhlInfo['sizeUnit'] = 'I';

                if (strtolower($sellerDhlInfo['sizeUnit']) == 'cm')
                  $sellerDhlInfo['sizeUnit'] = 'C';

                if (strtolower($sellerDhlInfo['weightUnit']) == 'kg')
                $sellerDhlInfo['weightUnit'] = 'K';

                if (strtolower($sellerDhlInfo['weightUnit']) == 'lb')
                  $sellerDhlInfo['weightUnit'] = 'L';

                // create xml data for api
                $data =
                '<?xml version="1.0" encoding="UTF-8" ?>
                  <req:ShipmentValidateRequest xmlns:req="http://www.dhl.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.dhl.com ship-val-req.xsd">
                  <Request>
                    <ServiceHeader>
                      <SiteID>' . $sellerDhlInfo['accessId'] . '</SiteID>
                      <Password>' . $sellerDhlInfo['password'] . '</Password>
                    </ServiceHeader>
                  </Request>
                  <RequestedPickupTime>N</RequestedPickupTime>
                  <NewShipper>N</NewShipper>
                  <LanguageCode>EN</LanguageCode>
                  <PiecesEnabled>Y</PiecesEnabled>
                  <Billing>
                    <ShipperAccountNumber>' . $sellerDhlInfo['accountNumber'] . '</ShipperAccountNumber>
                    <ShippingPaymentType>S</ShippingPaymentType>
                    <BillingAccountNumber>' . $sellerDhlInfo['accountNumber'] . '</BillingAccountNumber>
                    <DutyPaymentType>S</DutyPaymentType>
                    <DutyAccountNumber>' . $sellerDhlInfo['accountNumber'] . '</DutyAccountNumber>
                  </Billing>
                  <Consignee>
                    <CompanyName>' . $orderInfo->getBillingAddress()->getFirstName() . ' ' . $orderInfo->getBillingAddress()->getLastName() . '</CompanyName>
                    <AddressLine>' . $orderInfo->getBillingAddress()->getCity() . '</AddressLine>
                    <City>' . $orderInfo->getBillingAddress()->getCity() . '</City>
                    <Division>S</Division>
                    <PostalCode>' . $orderInfo->getBillingAddress()->getZipCode() . '</PostalCode>
                    <CountryCode>' . $orderInfo->getBillingAddress()->getCountry()->getIso() . '</CountryCode>
                    <CountryName>' .$orderInfo->getBillingAddress()->getCountry()->getName() . '</CountryName>
                    <Contact>
                      <PersonName>' . $orderInfo->getBillingAddress()->getFirstName() . ' ' . $orderInfo->getBillingAddress()->getLastName() . '</PersonName>
                      <PhoneNumber>' . $orderInfo->getBillingAddress()->getPhoneNumber() . '</PhoneNumber>
                    </Contact>
                  </Consignee>
                  <Commodity>
                    <CommodityCode>1</CommodityCode>
                  </Commodity>
                  <Dutiable>
                    <DeclaredValue>'.$productGrossPrice.'</DeclaredValue>
                    <DeclaredCurrency>' . $orderInfo->getCurrency()->getIsoCode() . '</DeclaredCurrency>
                  </Dutiable>
                  <Reference>
                    <ReferenceID>shipment reference</ReferenceID>
                    <ReferenceType>St</ReferenceType>
                  </Reference>
                  <ShipmentDetails>
                    <NumberOfPieces>1</NumberOfPieces>
                    <Pieces>';
                    if ($quantity > 1) {
                      for ($i =1; $i <= $quantity; $i++) {
                        $data.= '
                          <Piece>
                          <PieceID>1</PieceID>
                          <PackageType>EE</PackageType>
                          <Weight>' . $ProductWeight . '</Weight>
                          <Width>' . $productInfo->getWidth() . '</Width>
                          <Height>' . $productInfo->getHeight() . '</Height>
                          <Depth>' . $productInfo->getLength() . '</Depth>
                          <PieceContents>Webkul Test Seller Simple Product</PieceContents>
                        </Piece>';
                      }
                    } else{
                      $data.= '
                          <Piece>
                          <PieceID>1</PieceID>
                          <PackageType>EE</PackageType>
                          <Weight>' . $ProductWeight . '</Weight>
                          <Width>' . $productInfo->getWidth() . '</Width>
                          <Height>' . $productInfo->getHeight() . '</Height>
                          <Depth>' . $productInfo->getLength() . '</Depth>
                          <PieceContents>Webkul Test Seller Simple Product</PieceContents>
                        </Piece>';
                    }
                    $data .= '</Pieces>
                    <Weight>' . $ProductWeight . '</Weight>
                    <WeightUnit>' . $sellerDhlInfo['weightUnit'] . '</WeightUnit>
                    <GlobalProductCode>Q</GlobalProductCode>
                    <LocalProductCode>Q</LocalProductCode>
                    <Date>' . date('Y-m-d') . '</Date>
                    <Contents>DHL Parcel</Contents>
                    <DoorTo>DD</DoorTo>
                    <DimensionUnit>' . $sellerDhlInfo['sizeUnit'] . '</DimensionUnit>
                    <PackageType>EE</PackageType>
                    <IsDutiable>N</IsDutiable>
                    <CurrencyCode>' . $orderInfo->getCurrency()->getIsoCode() . '</CurrencyCode>
                  </ShipmentDetails>
                <Shipper>
                  <ShipperID>' . $sellerDhlInfo['accountNumber'] . '</ShipperID>
                  <CompanyName>webkul</CompanyName>
                  <RegisteredAccount>' . $sellerDhlInfo['accountNumber'] . '</RegisteredAccount>
                  <AddressLine>' . $sellerDhlInfo['city'] . '</AddressLine>
                  <City>' . $sellerDhlInfo['city'] . '</City>
                  <Division>S</Division>
                  <PostalCode>' . $sellerDhlInfo['postCode'] . '</PostalCode>
                  <CountryCode>' . $sellerDhlInfo['countryCode'] . '</CountryCode>
                  <CountryName>' . $sellerDhlInfo['countryName'] . '</CountryName>
                  <Contact>
                    <PersonName>' . $sellerDhlInfo['name'] . '</PersonName>
                    <PhoneNumber>9670336959</PhoneNumber>
                  </Contact>
                </Shipper>
              <LabelImageFormat>PDF</LabelImageFormat>
              <RequestArchiveDoc>Y</RequestArchiveDoc>';
            // if ($this->systemConfigService->get('WebkulMPDhlShipping.config.customLogo') && $sellerDhlInfo['logo']) {
            //   $data .= '
            //   <Label>
            //     <LabelTemplate>8X4_thermal</LabelTemplate>
            //     <Logo>Y</Logo>
            //     <CustomerLogo>
            //       <LogoImage>' . base64_encode($sellerDhlInfo['logo']) . '</LogoImage>
            //       <LogoImageFormat>' . strtoupper($extension) . '</LogoImageFormat>
            //     </CustomerLogo>
            //     <Resolution>200</Resolution>
            //   </Label>';
            // }
            $data .= '
            </req:ShipmentValidateRequest>';
            $tuCurl = curl_init();
            curl_setopt($tuCurl, CURLOPT_URL, $url);
            curl_setopt($tuCurl, CURLOPT_PORT , 443);
            curl_setopt($tuCurl, CURLOPT_VERBOSE, 0);
            curl_setopt($tuCurl, CURLOPT_HEADER, 0);
            curl_setopt($tuCurl, CURLOPT_POST, 1);
            curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $data);
            curl_setopt($tuCurl, CURLOPT_HTTPHEADER, array("Content-Type: text/xml","SOAPAction: \"/soap/action/query\"", "Content-length: " . strlen($data)));
  
            $tuData = curl_exec($tuCurl);
            $xml = simplexml_load_string($tuData);
            //dump($xml);die;
            $targetDirectory = $this->container->get('kernel')->getProjectDir() . '/public/dhl-shipping-label/';
            $labelFile = $targetDirectory .'/'. $orderNumber;
            if (!is_dir($labelFile))
            mkdir($labelFile, 0777, true);
            if (!is_file($labelFile . '/' . $productId . '.pdf')) {

              $filePath = fopen($labelFile . '/' . $productId . '.pdf', 'wb' );
              fwrite($filePath, base64_decode((string)$xml->LabelImage->OutputImage));
              fclose($filePath);
             if ($filePath) {
                $filePathData = [
                  'customerId' => $seller->get('customer')->getId(),
                  'orderId' => $orderInfo->getId(),
                  'productId' => $productId,
                  'labelPath' => $labelFile . '/' . $productId . '.pdf'
                ];
                $this->container->get('marketplace_seller_shipping_label.repository')->create([$filePathData], Context::createDefaultContext());
              } 
              $json['success'] = $this->trans('dhl-shipping.shippingLabelCreatedMessage');
            } else {
              $json['warning'] = $this->trans('dhl-shipping.shippingLabelExistMessage');
            }

            
          }
          
        }
        return new JsonResponse($json);
    }

    
}