<?php declare(strict_types=1);

namespace Webkul\MultiVendor\Core\Content\Bundle;

use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;

class CommissionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'marketplace_commission';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new BoolField('is_paid', 'isPaid')),
            new FloatField('commission_amount', 'commissionAmount'),
            new FloatField('seller_earning', 'sellerEarning'),
            new IdField('transaction_id', 'transactionId'),
            new StringField('transaction_comment', 'transactionComment'),
            (new FkField('marketplace_order_id', 'marketplaceOrderId', MpOrderDefinition::class))->addFlags(new Required()),
            (new FkField('marketplace_seller_id', 'marketplaceSellerId', SellerDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('marketplace_order', 'marketplace_order_id', MpOrderDefinition::class, 'id', false),
            new ManyToOneAssociationField('marketplace_seller', 'marketplace_seller_id', SellerDefinition::class, 'id', false)
            
        ]);
    }
}
