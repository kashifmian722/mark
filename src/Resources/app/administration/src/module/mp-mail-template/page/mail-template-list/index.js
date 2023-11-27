const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

import template from './mail-template-list.html.twig';

Component.register('mail-template-list', {
    template,

    inject: [
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    data() {
        return {
            total: 0,
            mpMailTemplateCollection: null,
            repository: null
        };
    },

	created() {
        this.getMarketplaceMailTemplate();
    },

	computed: {
        columns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                label: this.$t('mp-mail.list.nameColumn'),
                allowResize: true,
                sortable: false,
                },
                {
                property: 'subject',
                dataIndex: 'subject',
                label: this.$t('mp-mail.list.subjectColumn'),
                allowResize: true,
                sortable: false,
                },
            ];
        }
    },

    methods: {
    getMarketplaceMailTemplate: function () {
        const criteria = new Criteria();
        this.repository = this.repositoryFactory.create('marketplace_email_template');
        this.repository.search(criteria, Shopware.Context.api).then((result) =>{
               
                this.mpMailTemplateCollection = result;
                this.total = result.total;
            })
        }
    }
});
