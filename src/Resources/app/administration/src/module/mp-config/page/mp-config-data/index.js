const { Component, Mixin, Context } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;

import template from './mp-config.html.twig';

Component.register('mp-config-data',  {
    template,
    inject: ['repositoryFactory','WkMarketplaceApiService'],
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
            uploadTag: 'wk-mp-upload-tag',
            feature1UploadTag: 'wk-mp-feature1-tag',
            feature2UploadTag: 'wk-mp-feature2-tag',
            feature3UploadTag: 'wk-mp-feature3-tag',
            feature4UploadTag: 'wk-mp-feature4-tag',
            avatarMediaItem: null,
            feature1IconMedia: null,
            feature2IconMedia: null,
            feature3IconMedia: null,
            feature4IconMedia: null,
            saleschannelId: null,
            config: {
                headTitle: null,
                displayBanner: null,
                bannerImageId: null,
                bannerContent: null,
                pageLabel1: null,
                pageLabel2: null,
                pageLabel3: null,
                pageLabel4: null,
                buttonLabel: null,
                displayIcon: null,
                feature1Icon: null,
                feature1Label: null,
                feature2Icon: null,
                feature2Label: null,
                feature3Icon: null,
                feature3Label: null,
                feature4Icon: null,
                feature4Label: null,
                aboutMarketplace: null,
                manageOrderStatus: null,
                orderStateIds: null,
                completeStateId: null,
                cancelStateId: null,
                sellerListingTopHeading: null
            },
            salesChannelCollection: null,
            orderStateIds: null,
            orderStateCollection: null,
            orderStateMachineId: null
        }
    },
    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media')
        },
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel')
        },
        systemConfigRepository() {
            return this.repositoryFactory.create('system_config');
        },
        stateRepository() {
            return this.repositoryFactory.create('state_machine');
        },
        stateMachineRepository() {
            return this.repositoryFactory.create('state_machine_state');
        },
        stateMachineCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('technicalName','order.state'))
            this.stateRepository.search(criteria,Shopware.Context.api).then(result=>{
                this.orderStateMachineId = result[0].id;
            })
            const machineCriteria = new Criteria();
            return machineCriteria.addFilter(Criteria.equals('stateMachineId',this.orderStateMachineId));
            
        }
    },


    created() {
        this.getConfig();
        this.getSalesChannel();
        this.createdComponent();
    },
    methods: {
        createdComponent() {
            this.orderStateCollection = new EntityCollection(
                this.stateMachineRepository.route,
                this.stateMachineRepository.entityName,
                Context.api
            );
        },
        setStateIds(states) {
            this.config.orderStateIds = states.getIds();
            this.orderStateCollection = states;
        },
        async getSalesChannel() {
            await this.salesChannelRepository.search((new Criteria()),Shopware.Context.api).then((result)=>{
                this.salesChannelCollection = result

            })
        },
        async getConfig() {
            this.isLoading = true;
            const criteria = new Criteria(1,100);
            criteria.setTerm('WebkulMVMarketplace.config.');
            criteria.addFilter(Criteria.equals('salesChannelId',this.saleschannelId));
            await this.systemConfigRepository.search(criteria, Shopware.Context.api).then((result) => {
                
                result.forEach(element => {
                    if(element.configurationKey == 'WebkulMVMarketplace.config.headTitle')
                        this.config.headTitle = element.configurationValue;
                    if(element.configurationKey == 'WebkulMVMarketplace.config.displayBanner')
                        this.config.displayBanner = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.bannerImageId')
                        this.config.bannerImageId = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.bannerContent')
                        this.config.bannerContent = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.pageLabel1')
                        this.config.pageLabel1 = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.pageLabel2')
                        this.config.pageLabel2 = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.pageLabel3')
                        this.config.pageLabel3 = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.pageLabel4')
                        this.config.pageLabel4 = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.buttonLabel')
                        this.config.buttonLabel = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.displayIcon')
                        this.config.displayIcon = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.feature1Icon')
                        this.config.feature1Icon = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.feature2Icon')
                        this.config.feature2Icon = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.feature3Icon')
                        this.config.feature3Icon = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.feature4Icon')
                        this.config.feature4Icon = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.feature1Label')
                        this.config.feature1Label = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.feature2Label')
                        this.config.feature2Label = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.feature3Label')
                        this.config.feature3Label = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.feature4Label')
                        this.config.feature4Label = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.aboutMarketplace')
                        this.config.aboutMarketplace = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.manageOrderStatus')
                        this.config.manageOrderStatus = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.orderStateIds')
                        this.config.orderStateIds = element.configurationValue
                    if(element.configurationKey == 'WebkulMVMarketplace.config.sellerListingTopHeading')
                        this.config.sellerListingTopHeading = element.configurationValue
                    if(this.config.bannerImageId) 
                        this.setMediaItem({targetId: this.config.bannerImageId})
                    if(this.config.feature1Icon)
                        this.setFeatureIcon1({targetId: this.config.feature1Icon})
                    if(this.config.feature2Icon)
                        this.setFeatureIcon2({targetId:this.config.feature2Icon})
                    if(this.config.feature3Icon)
                        this.setFeatureIcon3({targetId:this.config.feature3Icon})
                    if(this.config.feature4Icon)
                        this.setFeatureIcon4({targetId:this.config.feature4Icon})
                    if( this.config.orderStateIds) {
                        this.stateMachineRepository.search((new Criteria()).addFilter(Criteria.equalsAny('id',this.config.orderStateIds)),Shopware.Context.api).then(result=>{
                            this.orderStateCollection = result
                        })
                    }
                });
            })
            this.isLoading = false;
        },
        onSaveConfig() {
            if(this.config.buttonLabel == '') {
                this.createNotificationInfo({
                    title: 'Info',
                    message: 'Marketplace landing page button label is required'
                })
                return;
            }
            this.isLoading = true;
            this.processSuccess = true;
            
            this.WkMarketplaceApiService.saveConfig(this.config,this.saleschannelId).then(result=>{
                this.createNotificationSuccess({
                    title: this.$t("wk-mp-config.label.successTitle"),
                    message: this.$t("wk-mp-config.label.successMessage")
                })
                this.isLoading = false;
                this.processSuccess = false;
            })
        },
        changeSaleschannel() {
            this.getConfig()
        },
        setMediaItem({
            targetId
        }) {
            this.mediaRepository.get(targetId, Shopware.Context.api).then((response) => {
                this.avatarMediaItem = response;
            });
            this.config.bannerImageId = targetId;
        },
        setMediaFromSidebar(mediaItem){
            this.avatarMediaItem = mediaItem;
            this.config.bannerImageId = mediaItem.id;
        },
        onDropMedia(mediaItem) {
            this.setMediaItem({
                targetId: mediaItem.id
            });
        },
        onUnlinkAvatar() {
            this.avatarMediaItem = null;
            this.config.bannerImageId = null;
        },
        removeMedia() {
            this.avatarMediaItem = null,
            this.config.bannerImageId = null
        },
        setFeatureIcon1({targetId}){
            this.mediaRepository.get(targetId, Shopware.Context.api).then((response) => {
                this.feature1IconMedia = response;
            });
            this.config.feature1Icon = targetId
        },
        // setFeatureIcon1FromSidebar(mediaItem){
        //     this.feature1IconMedia = mediaItem;
        //     this.config.feature1Icon = mediaItem.id;
        // },
        setFeatureIcon1FromMediaLibrary(selection){
            this.setFeatureIcon1({
                targetId: selection[0].id
            })
        },
        removeMedia1() {
            this.feature1IconMedia = null
            this.config.feature1Icon = null
        },
        setFeatureIcon2({targetId}){
            this.mediaRepository.get(targetId, Shopware.Context.api).then((response) => {
                this.feature2IconMedia = response;
            });
            this.config.feature2Icon = targetId
        },
        setFeatureIcon2FromMediaLibrary(selection){
            this.setFeatureIcon2({
                targetId: selection[0].id
            })
        },
        removeMedia2() {
            this.feature2IconMedia = null
            this.config.feature2Icon = null
        },
        setFeatureIcon3({targetId}){
            this.mediaRepository.get(targetId, Shopware.Context.api).then((response) => {
                this.feature3IconMedia = response;
            });
            this.config.feature3Icon = targetId
        },
        setFeatureIcon3FromMediaLibrary(selection){
            this.setFeatureIcon3({
                targetId: selection[0].id
            })
        },
        removeMedia3() {
            this.feature3IconMedia = null
            this.config.feature3Icon = null
        },
        setFeatureIcon4({targetId}){
            this.mediaRepository.get(targetId, Shopware.Context.api).then((response) => {
                this.feature4IconMedia = response;
            });
            this.config.feature4Icon = targetId
        },
        setFeatureIcon4FromMediaLibrary(selection){
            this.setFeatureIcon4({
                targetId: selection[0].id
            })
        },
        removeMedia4() {
            this.feature4IconMedia = null
            this.config.feature4Icon = null
        },
    }
})