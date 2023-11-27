const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

import template from './transaction-history-list.html.twig';

Component.register('transaction-history-list', {
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
        return{
            commissionRepository: null,
            transactionCollection: null,
            total: 0
        }

    },
    created(){
        this.getTransactionHistory();
    },
    computed: {
        columns(){
            return [
                {
                    property: 'customerName',
                    dataIndex: 'customerName',
                    label: this.$t('mp-transaction-history.list.columnCollection[0]'),
                    sortable: false,
                    allowResize: true
                },
                {
                    property: 'transactionId',
                    dataIndex: 'transactionId',
                    label: this.$t('mp-transaction-history.list.columnCollection[1]'),
                    sortable: false,
                    allowResize: true
                },
                {
                    property: 'transactionComment',
                    dataIndex: 'transactionComment',
                    label: this.$t('mp-transaction-history.list.columnCollection[2]'),
                    sortable: false,
                    allowResize: true
                },
                {
                    property: 'sellerEarning',
                    dataIndex: 'sellerEarning',
                    label: this.$t('mp-transaction-history.list.columnCollection[3]'),
                    sortable: false,
                    allowResize: true
                },
                {
                    property: 'updatedAt',
                    dataIndex: 'updatedAt',
                    label: this.$t('mp-transaction-history.list.columnCollection[4]'),
                    sortable: false,
                    allowResize: true
                }
            ]
        }
    },
    methods:{

        getTransactionHistory: function(){
            this.commissionRepository = this.repositoryFactory.create('marketplace_commission');
            const criteria = new Criteria();
            criteria.addAssociation('marketplace_seller.customer');
            criteria.addAssociation('marketplace_order.currency');
            criteria.addFilter(Criteria.equals('isPaid', true))
            this.commissionRepository.search(criteria, Shopware.Context.api).then((result)=>{
                this.transactionCollection = result;
                this.total = result.total;
            })
        }
    }


})