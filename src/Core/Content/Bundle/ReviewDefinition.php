<?php declare(strict_types=1);

namespace Webkul\MultiVendor\Core\Content\Bundle;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;

class ReviewDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'marketplace_review';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new IntField('review_rating', 'reviewRating'),
            new StringField('review_title', 'reviewTitle'),
            new LongTextField('review_description', 'reviewDescription'),
            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new Required()),
            (new FkField('marketplace_seller_id', 'marketplaceSellerId', SellerDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class),
            new ManyToOneAssociationField('marketplace_seller', 'marketplace_seller_id', SellerDefinition::class)
        ]);
    }
}
