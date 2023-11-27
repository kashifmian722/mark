const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

import template from './mp-order-list.html.twig';
import './mp-order-list.scss';

Component.register('mp-order-list', {
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
            orderListLoader: false,
            productRepository: {},
            marketplaceOrderCollection: null,
            isModalLoading: false,
            showModal: false,
            transactionMessage: null,
            baseUrl : null,
            shippingLabelReposiotry: null,
            dhlAddon: false,
            isLoading: false
        };
    },

	created() {
        let origin = window.location.origin;
        let pathname = window.location.pathname.replace('admin', '');
        this.baseUrl = origin + pathname + 'dhl-shipping-label/';
        console.log(this.baseUrl)
        this.repositoryFactory.create('plugin').search((new Criteria).addFilter(Criteria.equals('name','WebkulMPDhlShipping')).addFilter(Criteria.equals('active',1)), Shopware.Context.api).then(result=>{
            if(result.total > 0) {
                this.dhlAddon = true;
            }  
          })
        this.getMarketplaceOrders();
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
                }, {
                    property: 'marketplace_order.state_machine_state.name',
                    dataIndex: 'orderStatus',
                    label: this.$t('mp-order.list.columnCollection[8]'),
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
                }, {
                    property:'shippingLabel',
                    dataIndex: 'shippingLabel',
                    sortable: false,
                    visible: this.dhlAddon,
                    label: this.$t('mp-order.list.columnCollection[8]')
                }];
        }
    },

    methods: {
        getMarketplaceOrders: function () {
            this.productRepository = this.repositoryFactory.create('product');
            this.orderRepository = this.repositoryFactory.create('marketplace_order');
            this.commissionRepository = this.repositoryFactory.create('marketplace_commission');
            this.shippingLabelReposiotry = this.repositoryFactory.create('marketplace_seller_shipping_label');

            const orderSearchCriteria = new Criteria()
            
            orderSearchCriteria.addAssociation('marketplace_order.order_line_item');
            orderSearchCriteria.addAssociation('marketplace_order.marketplace_product.product'); 
            orderSearchCriteria.addAssociation('marketplace_order.marketplace_product.marketplace_seller'); 
            orderSearchCriteria.addAssociation('marketplace_order.currency'); 
            orderSearchCriteria.addAssociation('marketplace_order.order'); 
                     
            orderSearchCriteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            if (this.$route.query.customerId) {
                orderSearchCriteria.addFilter(Criteria.equals('marketplace_commission.marketplace_order.marketplace_product.marketplace_seller.customerId', this.$route.query.customerId))
            }

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
                    let shippingLabelCriteria = new Criteria();
                    shippingLabelCriteria.addFilter(Criteria.equals('orderId',order.marketplace_order.orderId));
                    shippingLabelCriteria.addFilter(Criteria.equals('productId', product.id))
                    this.shippingLabelReposiotry.search(shippingLabelCriteria,Shopware.Context.api).then((response)=>{
                        if(response.total > 0) {
                            let shippingLabel = true;
                            this.marketplaceOrderCollection[iteration]['shippingLabel'] = shippingLabel;
                        } 
                    })
                    

                });
            });
        },
        
        getCurrencies() {
            const criteria = new Criteria();
            this.currencyRepository.search(criteria, Shopware.Context.api).then((response) => {
                this.currencies = response;
            });
        },
        openModal(id, orderId) {
            this.showModal = true;
            this.commissionId = id;
            this.marketplaceOrderId = orderId;
        },
        paySeller(transactionMessage) {
                this.WkMarketplaceApiService.saveTransaction(this.commissionId, this.transactionMessage).then(result => {
                    this.showModal = false;
                    this.getMarketplaceOrders();
                    this.createNotificationSuccess({
                        title: this.$tc('mp-order.detail.titleSuccess'),
                        message: this.$tc('mp-order.detail.messageSaveSuccess')
                    });
                })
         },
        cancelModal() {
            this.showModal = false;
        },
        createShippingLabel(orderNumber,productId,quantity, sellerId, productPrice) {
            this.isLoading = true;
            this.WkMarketplaceApiService.createShippingLabel(orderNumber,productId,quantity, sellerId, productPrice).then(result=>{
                if(result.warning){
                    this.createNotificationWarning({
                        title: this.$tc('mp-order.detail.titleWarning'),
                        message: this.$tc('mp-order.list.shippingLabelWarningMessage')
                    });
                } else if(result.success){
                    this.createNotificationSuccess({
                        title: this.$tc('mp-order.detail.titleSuccess'),
                        message: this.$tc('mp-order.list.shippingLabelSuccessMessage')
                    });
                } else {
                    this.createNotificationError({
                        title: this.$tc('mp-order.detail.titleError'),
                        message: result.error
                    });
                }
                this.isLoading = false;
                this.getMarketplaceOrders()
            })
        },
        printWindow: function () {		
            var newWin = window.frames[0];
            newWin.document.write('<body onload="window.print()"><iframe style="position:fixed; top:0px; left:0px; bottom:0px; right:0px; width:100%; height:100%; border:none; margin:0; padding:0; overflow:hidden; z-index:999999;" src=""></body>');
            newWin.document.close();
        }
    }
});
