const { Component, Mixin,Context } = Shopware;
const { Criteria,EntityCollection } = Shopware.Data;

import template from './mp-product-assign.html.twig';

Component.register('mp-product-assign',{
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
            isLoading: false,
            processSuccess: false,
            productCollection: null,
            productIds: null,
            sellerCollection: [],
            sellerId: null,
            mpProductEntity: null,
            marketplaceProductIds: []
        }
    },
    computed: {
         productCriteria() {
             this.mpProductRepository.search((new Criteria()),Shopware.Context.api).then(result=>{
                result.forEach(element => {
                    this.marketplaceProductIds.push(element.productId)
                });
            })
            const criteria = new Criteria();
              criteria.addFilter(Criteria.equals('parentId',null));
              if(this.marketplaceProductIds.length > 0) {
                  criteria.addFilter(Criteria.not('AND', [Criteria.equalsAny('id', this.marketplaceProductIds)]));
                }
              return criteria;
         },
        productRepository() {
                
            return this.repositoryFactory.create('product');
        },
        sellerRepository() {
            return this.repositoryFactory.create('marketplace_seller');
        },
        mpProductRepository() {
            return this.repositoryFactory.create('marketplace_product');
        }
        
    },
    created() {
        this.createdComponent()
    },
    methods: {
        createdComponent() {
            this.productCollection = new EntityCollection(
                this.productRepository.route,
                this.productRepository.entityName,
                Context.api
            );
            const criteria = new Criteria();
            criteria.addAssociation('customer');
            this.sellerRepository.search(criteria, Shopware.Context.api)
            .then(result => {
                result.forEach(element => {
                   this.sellerCollection.push({'id':element.id, 'sellerName':element.customer.firstName +' '+ element.customer.lastName});
                });
            })
        },
        setProductIds(products) {
            this.productIds = products.getIds();
            this.productCollection = products;
        },
        onClickSave() {
            if(this.productIds == undefined){
                this.createNotificationInfo({
                    title: this.$t('mp-product.detail.infoTitle'),
                    message:this.$t('mp-product.detail.selectProductMessage')
                })
                return
            }
            if(this.sellerId == null){
                this.createNotificationInfo({
                    title: this.$t('mp-product.detail.infoTitle'),
                    message: this.$t('mp-product.detail.selectSellerMessage')
                })
                return
            }
            this.productIds.forEach(element => {
                this.mpProductEntity = this.mpProductRepository.create(Shopware.Context.api);
                this.mpProductEntity.productId = element;
                this.mpProductEntity.marketplaceSellerId = this.sellerId;
                this.mpProductRepository.save(this.mpProductEntity,Shopware.Context.api).then(result=>{
                    this.$router.push({
                        name: 'mp.product.list'
                    });
                    this.createNotificationSuccess({
                        title: this.$t("mp-product.detail.successTitle"),
                        message: this.$t("mp-product.detail.assignProductSuccessMessage")
                    })
                })
            });
        }
    }
})