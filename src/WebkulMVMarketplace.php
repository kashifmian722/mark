<?php declare(strict_types=1);

namespace Webkul\MultiVendor;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Uuid\Uuid;

class WebkulMVMarketplace extends Plugin
{
    public function update(UpdateContext $updateContext): void
    {
        $connection = $this->container->get(Connection::class);
        $connection->executeUpdate('DELETE FROM `marketplace_email_template` ');
        $logo = $_SERVER['APP_URL'].'/bundles/webkulmvmarketplace/administration/images/webkul-logo.png';
        $connection->insert('marketplace_email_template',['id'=> Uuid::randomBytes(), 'technical_name'=> 'update_order_status', 'name'=>'Order status has been updated', 'subject'=> 'Order status has been updated', 'message'=> '<div style="color: rgb(102, 102, 102);"><p><br><img src="'.$logo.'" style="width: 334.005px; height: 71px;"></p><p style="font-size: 16px; color: rgb(32, 151, 197); border-bottom: 1px solid;">Marketplace Message from {config_name}</p><br>Hi&nbsp;<span style="color: rgb(32, 151, 197); font-size: 14px;">{config_owner}</span>,<br></div><div style="color: rgb(102, 102, 102);"><br></div><div style="color: rgb(102, 102, 102);">Order status has been updated by the seller.</div><div style="color: rgb(102, 102, 102);">These are order details of your product(s)</div><div style="color: rgb(102, 102, 102);"><br></div><div style="color: rgb(102, 102, 102);">{order}</div><div style="color: rgb(102, 102, 102);"><br>Thanks,<br><span style="color: rgb(32, 151, 197);">{config_name}</span></div>', 'created_at'=> (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('marketplace_email_template',['id'=> Uuid::randomBytes(), 'technical_name'=> 'customer_apply_sellership', 'name'=>'Customer Apply for SellerShip (To Admin)', 'subject'=> 'Customer Apply for PartnerShip', 'message'=> '<p style="color: rgb(102, 102, 102);"><br><img src="'.$logo.'" style="width: 338.71px; height: 72px;"></p><p style="font-size: 16px; color: rgb(32, 151, 197); border-bottom: 1px solid;">Marketplace Message from {config_name}</p><p><br style="color: rgb(102, 102, 102);"><span style="color: rgb(102, 102, 102);">Hi&nbsp;</span><span style="color: rgb(32, 151, 197); font-size: 14px;">{config_owner}</span><span style="color: rgb(102, 102, 102);">,</span><br style="color: rgb(102, 102, 102);"><br style="color: rgb(102, 102, 102);"><span style="color: rgb(102, 102, 102);">Customer Applied for PartnerShip and has been approved through Auto Approve seller -&nbsp;</span><span style="color: rgb(32, 151, 197);">&nbsp;<span style="font-weight: bold;">{seller_name}</span></span><span style="font-weight: bold; color: rgb(32, 151, 197);">&nbsp;</span><br style="color: rgb(102, 102, 102);"><br style="color: rgb(102, 102, 102);"><span style="color: rgb(102, 102, 102);">Thanks,</span><br style="color: rgb(102, 102, 102);"><span style="color: rgb(32, 151, 197);">{config_name}</span><br></p>', 'created_at'=> (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('marketplace_email_template',['id'=> Uuid::randomBytes(), 'technical_name'=> 'confirm_sellership_message', 'name'=>'Customer Apply for SellerShip (To Customer)', 'subject'=> 'Thanks For Applying for SellerShip', 'message'=> '<div style="color: rgb(102, 102, 102);"><p><img src="'.$logo.'" style="width: 334.005px; height: 71px;"><br></p><p style="font-size: 16px; color: rgb(32, 151, 197); border-bottom: 1px solid;">Marketplace Message from {config_name}</p><br>Hi&nbsp;<span style="color: rgb(32, 151, 197); font-size: 14px;">{seller_name}</span>,<br><br>Thanks for&nbsp;<span style="line-height: 20px;">registering at</span>&nbsp;<span style="color: rgb(32, 151, 197);"><span style="font-weight: bold;">{config_name}.&nbsp;</span></span>Your Request for Sellership has been Approved successfully.</div><div style="color: rgb(102, 102, 102);">Be ready to Add your Product at<span style="color: rgb(32, 151, 197);">&nbsp;<span style="font-weight: bold;">{config_name}</span></span><br><br>Thanks,<br><span style="color: rgb(32, 151, 197);">{config_name}</span></div>', 'created_at'=> (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('marketplace_email_template',['id'=> Uuid::randomBytes(), 'technical_name'=> 'low_stock_mail', 'name'=>'Low Stock Mail Notification', 'subject'=> 'Low Stock Mail', 'message'=> '<div style="color: rgb(102, 102, 102);"><p><img src="'.$logo.'" style="width: 334.005px; height: 71px;"></p><p style="font-size: 16px; color: rgb(32, 151, 197); border-bottom: 1px solid;">Marketplace Message from {config_name}</p><br>Hi {seller_name},<br><br>Your product {product_name} is at low stock. Kindly check for the same.&nbsp;</div><div style="color: rgb(102, 102, 102);"><br>Thanks,<br><span style="color: rgb(32, 151, 197);">{config_name}</span></div>', 'created_at'=> (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
    }
    

    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);
        if ($context->keepUserData()) {
            return;
        }

        $connection = $this->container->get(Connection::class);

        $connection->executeUpdate('DROP TABLE IF EXISTS `marketplace_commission`');
        $connection->executeUpdate('DROP TABLE IF EXISTS `marketplace_order`');
        $connection->executeUpdate('DROP TABLE IF EXISTS `marketplace_product`');
        $connection->executeUpdate('DROP TABLE IF EXISTS `marketplace_review`');
        $connection->executeUpdate('DROP TABLE IF EXISTS `marketplace_seller`');
        $connection->executeUpdate('DROP TABLE IF EXISTS `marketplace_email_template`');
    }
}
