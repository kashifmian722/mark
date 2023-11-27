const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

import template from './mail-template-detail.html.twig';

Component.register('mail-template-detail', {
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
            isLoading: false,
            processSuccess: false,
            repository: null,
            templateData: null
        }
    },
   
    created() {
        this.repository = this.repositoryFactory.create('marketplace_email_template');
        this.getDetail();
        

    },
    methods: {
        getDetail(){
            this.repository.get(this.$route.params.id, Shopware.Context.api)
            .then((entity) => {
            
                this.templateData = entity;
            })
        },
        onClickSave(){
            this.processSuccess = false;
             this.repository.save(this.templateData, Shopware.Context.api)
             .then(()=> {
                this.isLoading = false;
                this.processSuccess = false;
                this.createNotificationSuccess({
                    title: this.$tc('mp-mail.list.titleSaveSuccess'),
                    message: this.$tc('mp-mail.list.messageSaveSuccess')
                });
             })
        }
    }
})
