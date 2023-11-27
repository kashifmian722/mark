<?php declare(strict_types=1);

namespace Webkul\MultiVendor\Core\Content\Bundle;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class MailTemplateDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'marketplace_email_template';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('technical_name', 'technicalName'))->addFlags(new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new StringField('subject', 'subject'))->addFlags(new Required()),
            (new LongTextField('message', 'message'))->addFlags(new Required())->addFlags(new AllowHtml())
        ]);
    }
}