const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

import template from './mp-product-list.html.twig';
import './mp-product-list.scss';

Component.register('mp-product-list', {
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
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            approveProcess: false,
            disapproveProcess: false,
            mpProductRepository: null,
            productListLoader: false,
            currencies: [],
            marketplaceProductCollection: null,
            baseUrl: window.location.href.replace('admin' + window.location.hash, ''),
            selectedItems: [],
            isLoading: false,
            showDeleteModal: false,
            showBulkDeleteModal: false,
            isBulkLoading: false,
        };
    },

	created() {
        this.getCurrencies();
        this.getMarketplaceProducts();
    },

	computed: {
        columns() {
            return this.getProductColumns();
        },
        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        currenciesColumns() {
            return this.currencies.sort((a, b) => {
                return b.isSystemDefault ? 1 : -1;
            }).map(item => {
                return {
                    property: `price-${item.isoCode}`,
                    dataIndex: `price-${item.id}`,
                    label:  this.$t('mp-product.list.columnCollection[3]'),
                    routerLink: 'sw.product.detail',
                    allowResize: true,
                    visible: item.isSystemDefault,
                    align: 'right'
                };
            });
        }
    },

    methods: {
        getMarketplaceProducts: function () {
            this.mpProductRepository = this.repositoryFactory.create('marketplace_product');

            const productSearchCriteria = new Criteria()
                .addAssociation('marketplace_seller')
                .addAssociation('marketplace_seller.customer')
                .addAssociation('product')
                .addFilter(Criteria.equals('product.parentId', null))
                .addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            this.mpProductRepository.search(productSearchCriteria, Shopware.Context.api)
            .then(result => {
                this.total = result.total;
                this.marketplaceProductCollection = result;
                this.productListLoader = true;
            });
        },

        onInlineEditSave: function (record, productDetails) {
            this.WkMarketplaceApiService.saveProductStatus(productDetails.product.id, productDetails.product.active).then(result => {
                this.getMarketplaceProducts();

                this.createNotificationSuccess({
                    title: this.$tc('mp-product.list.titleSaveSuccess'),
                    message: this.$tc('mp-product.list.messageSaveSuccess')
                });
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('mp-product.list.titleSaveError'),
                    message: this.$tc('mp-product.list.messageSaveError')
                });
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

        getProductColumns() {
            return [
                {
                    property: 'productName',
                    dataIndex: 'productName',
                    label: this.$t('mp-product.list.columnCollection[0]'),
                    allowResize: true,
                    primary: true
                }, {
                    property: 'marketplaceSellerName',
                    dataIndex: 'marketplaceSellerName',
                    label: this.$t('mp-product.list.columnCollection[1]'),
                    sortable: false,
                    allowResize: true
                }, {
                    property: 'marketplaceSellerEmail',
                    dataIndex: 'marketplaceSellerEmail',
                    label: this.$t('mp-product.list.columnCollection[2]'),
                    sortable: false,
                    allowResize: true
                }, 
                ...this.currenciesColumns,
                {
                    property: 'stock',
                    dataIndex: 'stock',
                    label: this.$t('mp-product.list.columnCollection[4]'),
                    allowResize: true
                }, {
                    property: 'status',
                    dataIndex: 'status',
                    sortable: false,
                    label: this.$t('mp-product.list.columnCollection[5]'),
                    inlineEdit: 'boolean',
                    allowResize: true,
                }
                // , {
                //     property: 'viewProduct',
                //     dataIndex: 'viewProduct',
                //     label: this.$t('mp-product.list.columnCollection[6]'),
                //     allowResize: true
                // }
            ];
        },

        getCurrencies() {
            const criteria = new Criteria();
            this.currencyRepository.search(criteria, Shopware.Context.api).then((response) => {
                this.currencies = response;
            });
        },

        onSortColumn: function (column) {
            switch (column.property) {
                case 'productName':
                    this.sortBy = 'marketplace_product.product.name';
                    break;

                case 'marketplaceSellerName':
                    this.sortBy = 'marketplace_seller.customer.firstName';
                    break;

                case 'marketplaceSellerEmail':
                    this.sortBy = 'marketplace_seller.customer.email';
                    break;

                case 'status':
                    this.sortBy = 'marketplace_product.product.active';
                    break;

                default:
                    break;

            }

            if (this.sortDirection === 'ASC') {
                this.sortDirection = 'DESC';
            } else {
                this.sortDirection = 'ASC';
            }

            this.getMarketplaceProducts();
        },
        onSelectionChanged(selection) {
            for(const property in selection){
                if(selection.hasOwnProperty(property)){
                    this.selectedItems.push(selection[property].productId);
                }
            }
        },
        approveProduct(){
            this.approveProcess = true;
            var productIds = this.selectedItems;
            this.WkMarketplaceApiService.bulkSaveProductStatus(productIds,true).then(result => {
                this.approveProcess = false;
                this.getMarketplaceProducts();
                this.createNotificationSuccess({
                    title: this.$tc('mp-product.list.titleSaveSuccess'),
                    message: this.$tc('mp-product.list.messageSaveSuccess')
                });
            }).catch((error)=>{
                console.log(error)
            })
            
        },
        disapproveProduct(){     
            this.disapproveProcess = true;
            var productIds = this.selectedItems;
            this.WkMarketplaceApiService.bulkSaveProductStatus(productIds,false).then(result => {
                this.disapproveProcess = false;
                this.getMarketplaceProducts();
                this.createNotificationSuccess({
                    title: this.$tc('mp-product.list.titleSaveSuccess'),
                    message: this.$tc('mp-product.list.messageSaveSuccess')
                });
            }).catch((error)=>{
                console.log(error)
            })
            
        },
        onDelete(id) {
           
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
           
            this.showDeleteModal = false;
        },
        onConfirmDelete(id) {
            this.showDeleteModal = false;
            return this.WkMarketplaceApiService.deleteShopwareProduct(id).then(result => {
                this.getMarketplaceProducts();
                this.createNotificationSuccess({
                    title: this.$tc('mp-product.list.titleSaveSuccess'),
                    message: this.$tc('mp-product.list.messageDeleteSuccess')
                }); 
            });
            
        },
        deleteItems() {
            this.isBulkLoading = true;
            
            return this.WkMarketplaceApiService.bulkDeleteShopwareProduct(this.selectedItems).then(result => {
                this.showBulkDeleteModal = false;
                this.getMarketplaceProducts();
                this.createNotificationSuccess({
                    title: this.$tc('mp-product.list.titleSaveSuccess'),
                    message: this.$tc('mp-product.list.messageDeleteSuccess')
                });   
            });

        }

    }
});
