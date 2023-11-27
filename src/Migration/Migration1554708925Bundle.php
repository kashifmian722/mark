<?php declare(strict_types=1);

namespace Webkul\MultiVendor\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1554708925Bundle extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554708925;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
        CREATE TABLE IF NOT EXISTS `marketplace_seller` (
            `id` BINARY(16) NOT NULL,
            `customer_id` BINARY(16) NOT NULL,
            `store_owner` VARCHAR(200),
            `store_logo_media` BINARY(16) NULL,
            `store_banner_media` BINARY(16) NULL,
            `store_owner_media` BINARY(16)  NULL,
            `type` TINYINT(2) DEFAULT 1 NOT NULL,
            `store_slug` VARCHAR(50),
            `store_title` VARCHAR(500),
            `store_description` LONGTEXT COLLATE utf8mb4_unicode_ci NULL,
            `social_sites` json,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            `is_applied` BOOLEAN,
            `is_approved` BOOLEAN,
            `profile_status` BOOLEAN,
            `admin_commission` FLOAT DEFAULT 0 NOT NULL,
            UNIQUE (store_slug),
            UNIQUE (customer_id),
            CONSTRAINT `fk.customer.customer_id` FOREIGN KEY (`customer_id`)
                REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk.store.logo.media` FOREIGN KEY (`store_logo_media`)
                REFERENCES `media` (`id`) ,
            CONSTRAINT `fk.store.banner.media` FOREIGN KEY (`store_banner_media`)
                REFERENCES `media` (`id`) ,
            CONSTRAINT `fk.store.owner.media` FOREIGN KEY (`store_owner_media`)
                REFERENCES `media` (`id`) ,
            PRIMARY KEY (`id`)
            
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `marketplace_product` (
                `id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `marketplace_seller_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                CONSTRAINT `fk.marketplace_product.marketplace_seller_id` FOREIGN KEY (`marketplace_seller_id`)
                    REFERENCES `marketplace_seller` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.marketplace_product.product_id` FOREIGN KEY (`product_id`)
                    REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                PRIMARY KEY (`id`),
                UNIQUE (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `marketplace_order` (
                `id` BINARY(16) NOT NULL,
                `order_line_item_id` BINARY(16) NOT NULL,
                `order_id` binary(16) NOT NULL,
                `order_status` binary(16) DEFAULT NULL,
                `currency_id` binary(16) NOT NULL,
                `marketplace_product` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                CONSTRAINT `fk.marketplace_order.marketplace_product` FOREIGN KEY(`marketplace_product`)
                    REFERENCES `marketplace_product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.marketplace_order.order_line_item_id` FOREIGN KEY (`order_line_item_id`)
                    REFERENCES `order_line_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `marketplace_order.order_id` FOREIGN KEY (`order_id`) 
                    REFERENCES `order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `marketplace_order.currency_id` FOREIGN KEY (`currency_id`) 
                    REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                PRIMARY KEY (`id`),
                UNIQUE (order_line_item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `marketplace_review` (
                `id` BINARY(16) NOT NULL,
                `marketplace_seller_id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `review_rating` INT(1) NULL,
                `is_approved` BOOLEAN,
                `nick_name` VARCHAR(50),
                `review_title` VARCHAR(500),
                `review_description` LONGTEXT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                CONSTRAINT `fk.marketplace_review.marketplace_seller_id` FOREIGN KEY (`marketplace_seller_id`)
                    REFERENCES `marketplace_seller` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.marketplace_review.customer_id` FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `marketplace_commission` (
                `id` BINARY(16) NOT NULL,
                `marketplace_order_id` BINARY(16) NOT NULL,
                `marketplace_seller_id` BINARY(16) NOT NULL,
                `commission_amount` FLOAT NULL,
                `seller_earning` FLOAT NULL,
                `is_paid` BOOLEAN,
                `transaction_id` BINARY(16) NULL,
                `transaction_comment` VARCHAR(250) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                CONSTRAINT `fk.marketplace_commission.marketplace_order_id` FOREIGN KEY (`marketplace_order_id`)
                    REFERENCES `marketplace_order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.marketplace_commission.marketplace_seller_id` FOREIGN KEY (`marketplace_seller_id`)
                    REFERENCES `marketplace_seller` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
        $connection->executeUpdate('
        CREATE TABLE IF NOT EXISTS `marketplace_email_template` (
            `id` binary(16) NOT NULL,
            `technical_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
            `name`varchar(225) NOT NULL,
            `subject` varchar(225) NOT NULL,
            `message`LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
            `created_at` datetime(3) NOT NULL,
            `updated_at` datetime(3) DEFAULT NULL,
            PRIMARY KEY (`id`) 
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
        $connection->executeUpdate('DELETE FROM `marketplace_email_template` ');
        $logo = $_SERVER['APP_URL'].'/bundles/webkulmvmarketplace/administration/images/webkul-logo.png';
        $connection->insert('marketplace_email_template',['id'=> Uuid::randomBytes(), 'technical_name'=> 'update_order_status', 'name'=>'Order status has been updated', 'subject'=> 'Order status has been updated', 'message'=> '<div style="color: rgb(102, 102, 102);"><p><br><img src="'.$logo.'" style="width: 334.005px; height: 71px;"></p><p style="font-size: 16px; color: rgb(32, 151, 197); border-bottom: 1px solid;">Marketplace Message from {config_name}</p><br>Hi&nbsp;<span style="color: rgb(32, 151, 197); font-size: 14px;">{config_owner}</span>,<br></div><div style="color: rgb(102, 102, 102);"><br></div><div style="color: rgb(102, 102, 102);">Order status has been updated by the seller.</div><div style="color: rgb(102, 102, 102);">These are order details of your product(s)</div><div style="color: rgb(102, 102, 102);"><br></div><div style="color: rgb(102, 102, 102);">{order}</div><div style="color: rgb(102, 102, 102);"><br>Thanks,<br><span style="color: rgb(32, 151, 197);">{config_name}</span></div>', 'created_at'=> (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('marketplace_email_template',['id'=> Uuid::randomBytes(), 'technical_name'=> 'customer_apply_sellership', 'name'=>'Customer Apply for SellerShip (To Admin)', 'subject'=> 'Customer Apply for PartnerShip', 'message'=> '<p style="color: rgb(102, 102, 102);"><br><img src="'.$logo.'" style="width: 338.71px; height: 72px;"></p><p style="font-size: 16px; color: rgb(32, 151, 197); border-bottom: 1px solid;">Marketplace Message from {config_name}</p><p><br style="color: rgb(102, 102, 102);"><span style="color: rgb(102, 102, 102);">Hi&nbsp;</span><span style="color: rgb(32, 151, 197); font-size: 14px;">{config_owner}</span><span style="color: rgb(102, 102, 102);">,</span><br style="color: rgb(102, 102, 102);"><br style="color: rgb(102, 102, 102);"><span style="color: rgb(102, 102, 102);">Customer Applied for PartnerShip and has been approved through Auto Approve seller -&nbsp;</span><span style="color: rgb(32, 151, 197);">&nbsp;<span style="font-weight: bold;">{seller_name}</span></span><span style="font-weight: bold; color: rgb(32, 151, 197);">&nbsp;</span><br style="color: rgb(102, 102, 102);"><br style="color: rgb(102, 102, 102);"><span style="color: rgb(102, 102, 102);">Thanks,</span><br style="color: rgb(102, 102, 102);"><span style="color: rgb(32, 151, 197);">{config_name}</span><br></p>', 'created_at'=> (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('marketplace_email_template',['id'=> Uuid::randomBytes(), 'technical_name'=> 'confirm_sellership_message', 'name'=>'Customer Apply for SellerShip (To Customer)', 'subject'=> 'Thanks For Applying for SellerShip', 'message'=> '<div style="color: rgb(102, 102, 102);"><p><img src="'.$logo.'" style="width: 334.005px; height: 71px;"><br></p><p style="font-size: 16px; color: rgb(32, 151, 197); border-bottom: 1px solid;">Marketplace Message from {config_name}</p><br>Hi&nbsp;<span style="color: rgb(32, 151, 197); font-size: 14px;">{seller_name}</span>,<br><br>Thanks for&nbsp;<span style="line-height: 20px;">registering at</span>&nbsp;<span style="color: rgb(32, 151, 197);"><span style="font-weight: bold;">{config_name}.&nbsp;</span></span>Your Request for Sellership has been Approved successfully.</div><div style="color: rgb(102, 102, 102);">Be ready to Add your Product at<span style="color: rgb(32, 151, 197);">&nbsp;<span style="font-weight: bold;">{config_name}</span></span><br><br>Thanks,<br><span style="color: rgb(32, 151, 197);">{config_name}</span></div>', 'created_at'=> (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $connection->insert('marketplace_email_template',['id'=> Uuid::randomBytes(), 'technical_name'=> 'low_stock_mail', 'name'=>'Low Stock Mail Notification', 'subject'=> 'Low Stock Mail', 'message'=> '<div style="color: rgb(102, 102, 102);"><p><img src="'.$logo.'" style="width: 334.005px; height: 71px;"></p><p style="font-size: 16px; color: rgb(32, 151, 197); border-bottom: 1px solid;">Marketplace Message from {config_name}</p><br>Hi {seller_name},<br><br>Your product {product_name} is at low stock. Kindly check for the same.&nbsp;</div><div style="color: rgb(102, 102, 102);"><br>Thanks,<br><span style="color: rgb(32, 151, 197);">{config_name}</span></div>', 'created_at'=> (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
