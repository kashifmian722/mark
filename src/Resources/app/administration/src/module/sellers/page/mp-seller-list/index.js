const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

import template from './mp-seller-list.html.twig';
import './mp-seller-list.scss';

Component.register('mp-seller-list', {
    template,

    inject: [
        'repositoryFactory',
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
            processSuccess: false,
            orderRepository: null,
            sellerRepository: null,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            sellerListLoader: false,
            marketplaceSellerCollection: null,
            selectedItems:  {},
            currencies: [],
        };
    },

	created() {
        this.getSellerData();
    },

	computed: {
        typeOptions() {
            return [{
                'name': this.$tc('mp-seller.list.textTypeClient'),
                'value': 1
            }, {
                'name': this.$tc('mp-seller.list.textTypeCompany'),
                'value': 2
            }];
        },
        columns() {
            return [
                {
                    property: 'customerName',
                    dataIndex: 'customerName',
                    label: this.$t('mp-seller.list.columnCollection[0]'),
                    allowResize: true,
                    primary: true
                }, {
                    property: 'customerEmail',
                    dataIndex: 'customerEmail',
                    label: this.$t('mp-seller.list.columnCollection[1]'),
                    allowResize: true,
                }, {
                    property: 'isApplied',
                    dataIndex: 'isApplied',
                    label: this.$t('mp-seller.list.columnCollection[2]'),
                    allowResize: true
                }, {
                    property: 'isApproved',
                    label: this.$tc('mp-seller.list.columnCollection[3]'),
                    inlineEdit: 'boolean',
                    allowResize: true,
                    align: 'center'
                }, {
                    property: 'totalOrders',
                    dataIndex: 'totalOrders',
                    label: this.$t('mp-seller.list.columnCollection[4]'),
                    sortable: false,
                    allowResize: true,
                }, {
                    property: 'adminCommission',
                    dataIndex: 'adminCommission',
                    label: this.$t('mp-seller.list.columnCollection[7]'), 
                    inlineEdit: 'number',
                    allowResize: true
                }, {
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    label: this.$t('mp-seller.list.columnCollection[5]'),
                    allowResize: true,
                }, {
                    property: 'type',
                    dataIndex: 'type',
                    label: this.$t('mp-seller.list.columnCollection[6]'),
                    allowResize: true
                },
            ];
        }
    },

    methods: {
        getSellerData: function () {
            this.sellerRepository = this.repositoryFactory.create('marketplace_seller');

            const sellerSearchCriteria = new Criteria();
            sellerSearchCriteria.addAssociation('customer');
            sellerSearchCriteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));


            this.sellerRepository.search(sellerSearchCriteria, Shopware.Context.api)
            .then(result => {
                this.marketplaceSellerCollection = result;
                this.getSellersOrderCount();

                this.total = result.total;
                this.sellerListLoader = true;
            }).catch((exception) => {
                this.isLoading = false;
                this.total = result.total;
                this.createNotificationError({
                    title: this.$t('swag-bundle.detail.errorTitle'),
                    message: exception
                });
            });
        },
       

        getSellersOrderCount: function () {
            this.orderRepository = this.repositoryFactory.create('marketplace_order');

            const orderSearchCriteria = new Criteria();
            orderSearchCriteria.addAssociation('marketplace_product');
            orderSearchCriteria.addAssociation('marketplace_product.marketplace_seller');

            this.marketplaceSellerCollection.forEach((seller, index) => {
                orderSearchCriteria.filters = [];
                let criteria = orderSearchCriteria;
                criteria.addFilter(Criteria.equals('marketplace_product.marketplace_seller.id', seller.id))

                this.orderRepository.search(criteria, Shopware.Context.api)
                .then(result => {
                    this.marketplaceSellerCollection[index].ordersCount = result.length;
                }).catch((exception) => {
                    this.isLoading = false;
                    this.createNotificationError({
                        title: this.$t('swag-bundle.detail.errorTitle'),
                        message: exception
                    });
                });
            })
        },
        onSelectionChanged(selection) {
            this.selectedItems = selection;
        },
        approveSeller(){
            this.isLoading = true;
            let sellerIds = Object.keys(this.selectedItems);
            this.WkMarketplaceApiService.approveSellers(sellerIds,true).then(result => {
                this.isLoading = false;
                this.createNotificationSuccess({
                    title: this.$tc('mp-seller.list.titleSaveSuccess'),
                    message: this.$tc('mp-seller.list.approveSellerSuccessMessage')
                });
                this.getSellerData();
            }).catch((error)=>{
                console.log(error)
            })
            
        },
        disapproveSeller(){     
            this.isLoading = true;
            let sellerIds = Object.keys(this.selectedItems);
            this.WkMarketplaceApiService.approveSellers(sellerIds,false).then(result => {
                this.isLoading = false;
                this.createNotificationSuccess({
                    title: this.$tc('mp-seller.list.titleSaveSuccess'),
                    message: this.$tc('mp-seller.list.disapproveSellerMessage')
                });
                this.getSellerData();
            }).catch((error)=>{
                console.log(error)
            })
            
        },

        onInlineEditSave: function (record) {
            record.then(result => {
                // @TODO:- already one network was sent (we need to send to update our orders count)
                this.getSellerData();

                this.createNotificationSuccess({
                    title: this.$tc('mp-seller.list.titleSaveSuccess'),
                    message: this.$tc('mp-seller.list.messageSaveSuccess')
                });
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('mp-seller.list.titleSaveError'),
                    message: this.$tc('mp-seller.list.messageSaveError')
                });
            });
        },
        onInlineEditCancel: function () {
            this.getSellerData();
        },
        getCurrencies() {
            const criteria = new Criteria();
            this.currencyRepository.search(criteria, Shopware.Context.api).then((response) => {
                this.currencies = response;
            });
        },
        getCurrencyPriceByCurrencyId(itemId, currencyId) {
            let foundPrice = {
                currencyId: null,
                gross: null,
                linked: true,
                net: null
            };

            // check if products are loaded
            if (!this.marketplaceProductCollection) {
                return foundPrice;
            }

            // find product for itemId
            const foundProduct = this.marketplaceProductCollection.find((item) => {
                return item.id === itemId;
            });

            // find price from product with currency id
            if (foundProduct) {
                const priceForProduct = foundProduct.product.price.find((price) => {
                    return price.currencyId === currencyId;
                });

                if (priceForProduct) {
                    foundPrice = priceForProduct;
                }
            }

            // return the price
            return foundPrice;
        },


        onSortColumn: function (column) {
            switch (column.property) {
                case 'customerName':
                    this.sortBy = 'customer.firstName';
                    break;

                case 'customerEmail':
                    this.sortBy = 'customer.email';
                    break;
                default:
                    break;

            }

            if (this.sortDirection === 'ASC') {
                this.sortDirection = 'DESC';
            } else {
                this.sortDirection = 'ASC';
            }

            this.getSellerData();
        }
    }
});
