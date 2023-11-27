<?php declare(strict_types=1);

namespace Webkul\MultiVendor\Core\Content\Bundle;

use Shopware\Core\Content\Product\ProductDefinition as ShopwareProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;

class ProductDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'marketplace_product';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('marketplace_seller_id', 'marketplaceSellerId', SellerDefinition::class))->addFlags(new Required()),
            (new FkField('product_id', 'productId', ShopwareProductDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('product', 'product_id', ShopwareProductDefinition::class, 'id', false),
            new ManyToOneAssociationField('marketplace_seller', 'marketplace_seller_id', SellerDefinition::class),
        ]);
    }
}
