const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

import template from './mp-transaction-detail.html.twig';

Component.register('mp-transaction-detail', {
    template,

    inject: [
        'repositoryFactory',
        'systemConfigApiService',
        'WkMarketplaceApiService'
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
            commission: 0,
            currencies: [],
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            processSuccess: false,
            orderRepository: {},
            commissionRepository: {},
            stateMachineRepository: {},
            orderListLoader: false,
            productRepository: {},
            marketplaceOrderCollection: null,
            isModalLoading: false,
            showModal: false,
            transactionMessage: null,
            selectedItems: null,
            completeStateId: null,
            paybleAmount: null,
            commissionId: [],
            paymentStatus: null,
        };
    },

	created() {
        this.stateMachineRepository = this.repositoryFactory.create('state_machine_state');
        this.getStateId();
        this.getCurrencies();
        
    },

	computed: {
        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },
        columns() {
            return [{
                property: 'orderNumber',
                dataIndex: 'orderNumber',
                label: this.$t('mp-order.list.columnCollection[7]'),
                allowResize: true,
                sortable: false,
                },{
                    property: 'productName',
                    dataIndex: 'productName',
                    label: this.$t('mp-order.list.columnCollection[0]'),
                    allowResize: true,
                    sortable: false,
                    primary: true
                }, {
                    property: 'quantity',
                    dataIndex: 'quantity',
                    label: this.$t('mp-order.list.columnCollection[1]'),
                    allowResize: true,
                    sortable: false,
                }, {
                    property: 'totalAmount',
                    dataIndex: 'totalAmount',
                    label: this.$t('mp-order.list.columnCollection[2]'),
                    allowResize: true,
                    sortable: false,
                }, {
                    property: 'commissionAmount',
                    dataIndex: 'commissionAmount',
                    label: this.$t('mp-order.list.columnCollection[4]'),
                    allowResize: true,
                    sortable: false,
                },  {
                    property: 'sellerEarnings',
                    dataIndex: 'sellerEarnings',
                    label: this.$t('mp-order.list.columnCollection[3]'),
                    allowResize: true,
                    sortable: false,
                },{
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    label: this.$t('mp-order.list.columnCollection[5]'),
                    allowResize: true,
                    sortable: false,
                }, {
                property:'isPaid',
                dataIndex: 'isPaid',
                sortable: false,
                label: this.$t('mp-order.list.columnCollection[6]')
            }];
        }
    },

    methods: {
        getStateId: function () {
            const stateCriteria = new Criteria();
            stateCriteria.addFilter(Criteria.equals('technicalName', 'completed'));
            stateCriteria.addAssociation('stateMachine')
            stateCriteria.addFilter(Criteria.equals('stateMachine.technicalName', 'order.state'))
            this.stateMachineRepository.search(stateCriteria, Shopware.Context.api).then((result)=>{
                this.completeStateId = result[0].id;
                this.getMarketplaceOrders();
            })
        },
        getMarketplaceOrders: function () {
            this.productRepository = this.repositoryFactory.create('product');
            this.orderRepository = this.repositoryFactory.create('marketplace_order');
            this.commissionRepository = this.repositoryFactory.create('marketplace_commission');
            
            const orderSearchCriteria = new Criteria()
            if(this.paymentStatus != null){
                orderSearchCriteria.addFilter(Criteria.equals('isPaid', this.paymentStatus))
            }
            orderSearchCriteria.addFilter(Criteria.equals('marketplaceSellerId', this.$route.params.id));
            orderSearchCriteria.addAssociation('marketplace_order.order_line_item');
            orderSearchCriteria.addAssociation('marketplace_order.marketplace_product.product'); 
            orderSearchCriteria.addAssociation('marketplace_order.currency'); 
            orderSearchCriteria.addAssociation('marketplace_order.order');
            orderSearchCriteria.addFilter(Criteria.equals('marketplace_order.order.stateId', this.completeStateId))
            orderSearchCriteria.addFilter(Criteria.equals('marketplace_order.orderStatus', this.completeStateId))
                     
            orderSearchCriteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));


            this.commissionRepository.search(orderSearchCriteria, Shopware.Context.api)
            .then(result => {
                this.total = result.total;
                this.marketplaceOrderCollection = result;
                
                this.orderListLoader = true;

                this.marketplaceOrderCollection.forEach((order, iteration) => {
                    let productSearchCriteria = new Criteria();
                    let product = order.marketplace_order.marketplace_product.product;

                    if (!product.name) {
                        productSearchCriteria.addFilter(
                            Criteria.equals('id', product.parentId)
                        );

                        this.productRepository.search(productSearchCriteria, Shopware.Context.api).then(result => {
                            
                            this.marketplaceOrderCollection[iteration].marketplace_order.marketplace_product.product.name = result[0].name;
                        })

                    }

                });
            });
        },
        
        getCurrencies() {
            const criteria = new Criteria();
            this.currencyRepository.search(criteria, Shopware.Context.api).then((response) => {
                this.currencies = response;
            });
        },
        onSelectionChanged(selection) {
            
            this.paybleAmount = 0;
            this.commissionId = [];
            for(const property in selection){
                
                if(!selection[property].isPaid){
                this.commissionId.push(selection[property].id);
                this.paybleAmount += selection[property].sellerEarning;
                }
            }
        },  
        openModal() {
            this.showModal = true;
        },
        paySeller(transactionMessage) {
                this.WkMarketplaceApiService.saveTransaction(this.commissionId, this.transactionMessage).then(result => {
                    this.showModal = false;
                    this.getMarketplaceOrders();
                    this.createNotificationSuccess({
                        title: this.$tc('mp-transaction.detail.titleSaveSuccess'),
                        message: this.$tc('mp-transaction.detail.messageSaveSuccess')
                    });
                })
         },
        cancelModal() {
            this.showModal = false;
        },
        onChangeStatus(event){
            this.paymentStatus = event;
            this.getMarketplaceOrders()

        }

    }
});
