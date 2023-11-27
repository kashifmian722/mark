const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

import template from './mp-seller-detail.html.twig';

Component.register('mp-seller-detail', {
    template,

    inject: [
        'repositoryFactory'
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
            sellerInfo: null,
            adminCommission: null,
            isLoading: false,
            processSuccess: false,
            repository: null,
            storeOwner: 'store-owner'
        }
    },
   
    created() {
        this.repository = this.repositoryFactory.create('marketplace_seller');
        this.getSellers();
        

    },
    computed:{
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        

    },
   
    methods: {
        onSetMediaStore({ targetId }) {
            this.mediaRepository.get(targetId, Shopware.Context.api).then((updatedMedia) => {
                
                this.sellerInfo.storeLogoId = targetId;
                this.sellerInfo.mediaLogo = updatedMedia;
            });
        },

        onMediaDroppedStore(dropItem) {
            this.onSetMediaStore({ targetId: dropItem.id });
        },

        onRemoveMediaStore() {
            this.sellerInfo.storeLogoId = null;
            this.sellerInfo.mediaLogo = null;
        },
        onSetMediaBanner({ targetId }) {
            this.mediaRepository.get(targetId, Shopware.Context.api).then((updatedMedia) => {
                
                this.sellerInfo.storeBannerId = targetId;
                this.sellerInfo.mediaBanner = updatedMedia;
            });
        },

        onMediaDroppedBanner(dropItem) {
            this.onSetMediaBanner({ targetId: dropItem.id });
        },

        onRemoveMediaBanner() {
            this.sellerInfo.storeBannerId = null;
            this.sellerInfo.mediaBanner = null;
        },
        onSetMediaOwner({ targetId }) {
            this.mediaRepository.get(targetId, Shopware.Context.api).then((updatedMedia) => {
                
                this.sellerInfo.storeOwnerId = targetId;
                this.sellerInfo.mediaOwner = updatedMedia;
            });
        },

        onMediaDroppedOwner(dropItem) {
            this.onSetMediaOwner({ targetId: dropItem.id });
        },

        onRemoveMediaOwner() {
            this.sellerInfo.storeOwnerId = null;
            this.sellerInfo.mediaOwner = null;
        },

       getSellers() {
            this.repository.get(this.$route.params.id, Shopware.Context.api)
                .then((entity) => {
                
                    this.sellerInfo = entity;
                })
       },
        onClickSave() {
             this.isLoading = true;
             this.processSuccess = true;
             this.repository.save(this.sellerInfo, Shopware.Context.api)
             .then(()=> {
                this.isLoading = false;
                this.createNotificationSuccess({
                    title: this.$tc('mp-seller.list.titleSaveSuccess'),
                    message: this.$tc('mp-seller.list.messageSaveSuccess')
                });
                this.$router.push({
                    name: 'mp.seller.list'
                })
             })
        },
        saveFinish() {
            this.processSuccess = false;
        },
    }

})