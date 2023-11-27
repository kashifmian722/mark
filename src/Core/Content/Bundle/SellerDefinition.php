<?php declare(strict_types=1);

namespace Webkul\MultiVendor\Core\Content\Bundle;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;

class SellerDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'marketplace_seller';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('store_slug', 'storeSlug')),
            (new StringField('store_owner', 'storeOwner')),
            (new IntField('type', 'type')),
            (new StringField('store_title', 'storeTitle')),
            (new LongTextField('store_description', 'storeDescription')),
            (new JsonField('social_sites', 'socialSites')),
            (new BoolField('is_applied', 'isApplied'))->addFlags(new Required()),
            (new BoolField('is_approved', 'isApproved'))->addFlags(new Required()),
            (new BoolField('profile_status', 'profileStatus')),
            (new FloatField('admin_commission', 'adminCommission')),
            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new Required()),
            (new FkField('store_logo_media', 'storeLogoId', MediaDefinition::class)),
            (new FkField('store_banner_media', 'storeBannerId', MediaDefinition::class)),
            (new FkField('store_owner_media', 'storeOwnerId', MediaDefinition::class)),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),
            (new ManyToOneAssociationField('mediaLogo', 'store_logo_media', MediaDefinition::class)),
            (new ManyToOneAssociationField('mediaBanner', 'store_banner_media', MediaDefinition::class)),
            (new ManyToOneAssociationField('mediaOwner', 'store_owner_media', MediaDefinition::class))
        ]);
    }
}
