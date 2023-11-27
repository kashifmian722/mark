<?php

namespace Webkul\MultiVendor\Modules;

use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class ShopwareProductHelper
{
    private $container;

    public function __construct(
        ContainerInterface $container
    ) {
        $this->container = $container;
    }

    public function deleteProduct($productId)
    {
        $productRepository = $this->container->get('product.repository');

        $productRepository->delete([
            [
                'id' => $productId
            ]
        ], Context::createDefaultContext());

        return true;
    }

    public function deleteProductConfiguratorSetting($settingId)
    {
        $productConfiguratorSettingRepository = $this->container->get('product_configurator_setting.repository');

        $productConfiguratorSettingRepository->delete([
            [
                'id' => $settingId
            ]
        ], Context::createDefaultContext());

        return true;
    }

    public function findAllProductConfiguratorSettingByParentId($parentId)
    {
        $productConfiguratorSettingRepository = $this->container->get('product_configurator_setting.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('productId', $parentId));

        $productConfiguratorSettings = $productConfiguratorSettingRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->getElements();

        return $productConfiguratorSettings;
    }

    public function deleteProductAllVariant($productId)
    {
        $storefrontHelper = $this->container->get('storefront.helper');

        $productVariants = $storefrontHelper->fetchProductVariantList($productId);

        foreach ($productVariants as $productVariant) {
            $shopwareProductHelper = $this->container->get('shopware.product.helper');

            // delete all variants from product entity
            $shopwareProductHelper->deleteProduct($productVariant->getId());

            // fetch all configurator settings from product_configurator_setting entity
            $productConfiguratorSettings = $shopwareProductHelper->findAllProductConfiguratorSettingByParentId($productVariant->getParentId());

            // delete all configurator settings from product_configurator_setting entity
            foreach ($productConfiguratorSettings as $configuratorId => $configuratorDetails) {
                $shopwareProductHelper->deleteProductConfiguratorSetting($configuratorId);
            }
        }

        return true;
    }

    public function removeProductCategory($productId, $categoryIds)
    {
        $productRepository = $this->container->get('product_category.repository');

        foreach ($categoryIds as $categoryId) {
            $productRepository->delete([
                [
                    "productId" => $productId,
                    "categoryId" => $categoryId,
                ]
            ], Context::createDefaultContext());
        }

        return true;
    }
    public function removeProductMedia($productId, $mediaIds)
    {
       
        $productRepository = $this->container->get('product_media.repository');
        $criteria = (new Criteria())->addFilter(new EqualsAnyFilter('mediaId', $mediaIds));
        $productMedias = $productRepository->search($criteria, Context::createDefaultContext())->getElements();
        foreach ($productMedias as $id => $productMedia) {
            $mediaId[] =  ["id" => $id, "productId" => $productId];
        }
            $productRepository->delete($mediaId, Context::createDefaultContext());
        
        return true;
    }
}

?>
