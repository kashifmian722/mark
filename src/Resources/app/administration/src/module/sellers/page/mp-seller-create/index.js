const { Component, Mixin, Context } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;
import template from './mp-seller-create.html.twig';

Component.register('mp-seller-create',{
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
            customerIds: null,
            customerCollection: null,
            sellerIds: []
        }
    },
    created(){
        this.createdComponent();
    },
    computed: {
        customerRepository(){
            return this.repositoryFactory.create('customer');
        },
        sellerRepository() {
            return this.repositoryFactory.create('marketplace_seller');
        },
        customerCriteria() {
            
            const criteria = new Criteria();
              if(this.sellerIds.length > 0) {
                  criteria.addFilter(Criteria.not('AND', [Criteria.equalsAny('id', this.sellerIds)]));
                }
              return criteria;
        }
    },
    methods:{
        createdComponent() {
            this.sellerRepository.search((new Criteria),Shopware.Context.api).then(result=>{
                result.forEach(element => {
                    this.sellerIds.push(element.customerId);
                });
            })
            this.customerCollection = new EntityCollection(
                this.customerRepository.route,
                this.customerRepository.entityName,
                Context.api
            );
        },
        setCustomer(customers) {
            this.customerIds = customers.getIds();
            this.customerCollection = customers;
        },
        onClickSave(){
            if(this.customerIds == undefined){
                this.createNotificationInfo({
                    title: this.$t('mp-seller.create.infoTitle'),
                    message:this.$t('mp-seller.create.selectCustomerMessage')
                })
                return
            }
            this.customerIds.forEach(element => {
                this.sellerEntity = this.sellerRepository.create(Shopware.Context.api);
                this.sellerEntity.customerId = element;
                this.sellerRepository.save(this.sellerEntity,Shopware.Context.api);   
            });
            this.createNotificationSuccess({
                title: this.$t("mp-seller.create.successTitle"),
                message: this.$t("mp-seller.create.successMessage")
            })
            this.$router.push({
                name: 'mp.seller.list'
            });
            window.location.reload();
        },
        saveFinish() {
            this.processSuccess = false;
        },
       
    }
})