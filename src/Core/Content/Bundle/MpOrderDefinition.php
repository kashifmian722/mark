<?php declare(strict_types=1);

namespace Webkul\MultiVendor\Core\Content\Bundle;

use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

class MpOrderDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'marketplace_order';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('marketplace_product', 'marketplaceProductId', ProductDefinition::class))->addFlags(new Required()),
            (new FkField('order_line_item_id', 'orderLineItemId', OrderLineItemDefinition::class))->addFlags(new Required()),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required()),
            (new FkField('order_status', 'orderStatus', OrderDefinition::class, 'state_id'))->addFlags(new Required()),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('marketplace_product', 'marketplace_product', ProductDefinition::class, 'id', false),
            new ManyToOneAssociationField('order_line_item', 'order_line_item_id', OrderLineItemDefinition::class, 'id', false),
            new ManyToOneAssociationField('order', 'order_id',  OrderDefinition::class, 'id', false),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id', false),
            new ManyToOneAssociationField('state_machine_state', 'order_status', StateMachineStateDefinition::class, 'id', true),
        ]);
    }
}
