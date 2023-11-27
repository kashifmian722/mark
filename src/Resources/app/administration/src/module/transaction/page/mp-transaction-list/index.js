const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

import template from './mp-transaction-list.html.twig';

Component.register('mp-transaction-list', {
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
    data(){
        return{
            total: 0,
            page: 1,
            limit: 25,
            processSuccess: false,
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            transactionListLoader: false,
            marketplaceTransactionCollection: [],
            marketplaceIncome: [],
            selectedItems:  {},
            currencies: [],
            completeStateId: null,
            commissionRepository: null
        }
    },
    created() {
        this.stateMachineRepository = this.repositoryFactory.create('state_machine_state');
        this.getStateId();
    },
    computed: {
        columns(){
            return[{
                property: 'customerName',
                dataIndex: 'customerName',
                label: this.$t('mp-transaction.list.columnCollection[0]'),
                sortable: false,
                allowResize: true,
                },
               {
                    property: 'totalAmount',
                    dataIndex: 'totalAmount',
                    label: this.$t('mp-transaction.list.columnCollection[3]'),
                    sortable: false,
                    allowResize: true,
                }, {
                    property: 'adminAmount',
                    dataIndex: 'adminAmount',
                    label: this.$t('mp-transaction.list.columnCollection[4]'),
                    sortable: false,
                    allowResize: true
                }, {
                    property: 'sellerAmount',
                    dataIndex: 'sellerAmount',
                    label: this.$t('mp-transaction.list.columnCollection[5]'),
                    allowResize: true
                }, {
                    property: 'paidAmount',
                    dataIndex: 'Amount',
                    label: this.$t('mp-transaction.list.columnCollection[6]'),
                    allowResize: true
                },
                {
                    property: 'remainingAmount',
                    dataIndex: 'remainingAmount',
                    label: this.$t('mp-transaction.list.columnCollection[7]'),
                    allowResize: true
                },
                   
                
            ]
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
                this.getSellerTotalPayout();
            })
        },

        getSellerTotalPayout: function () {
            
            this.commissionRepository = this.repositoryFactory.create('marketplace_commission');
            const transactionSearchCriteria = new Criteria(this.page, this.limit);
            transactionSearchCriteria.addAssociation('marketplace_seller');
            transactionSearchCriteria.addAssociation('marketplace_order.order');
            transactionSearchCriteria.addAssociation('marketplace_seller.customer')
            transactionSearchCriteria.addAssociation('marketplace_order.currency'); 
            transactionSearchCriteria.addFilter(Criteria.equals('marketplace_order.order.stateId', this.completeStateId));
            transactionSearchCriteria.addFilter(Criteria.equals('marketplace_order.orderStatus', this.completeStateId))  
            
    
        
                this.commissionRepository.search(transactionSearchCriteria, Shopware.Context.api)
                .then(result => {
                    
                    let customer = null;
                    let marketplaceSellerId = null;
                    let currencyId = null;
                    const groupBy = (array, key) => {
                        return array.reduce((result, currentValue) => {
                          (result[currentValue[key]] = result[currentValue[key]] || []).push(
                            currentValue
                          );
                          return result;
                        }, {}); 
                      };
                      
                      // Group by sellerId as key to the result array
                      const resultGroup = groupBy(result, 'marketplaceSellerId');
                      
                    for(const property in resultGroup){
                        if(resultGroup.hasOwnProperty(property)){
                            let commissionAmount = 0;
                            let totalPayout = 0;
                            let paidAmount = 0;
                            let totalAmount = 0;
                            resultGroup[property].forEach((data, index)=>{
                            commissionAmount += data.commissionAmount;
                            totalPayout += data.sellerEarning;
                            
                            if(data.isPaid){

                                paidAmount += data.sellerEarning;
                            }
                                customer = data.marketplace_seller.customer 
                                marketplaceSellerId = data.marketplaceSellerId
                                
                                currencyId = data.marketplace_order.currency.isoCode;

                            })
                            totalAmount += commissionAmount+totalPayout;
                            this.marketplaceTransactionCollection.push({'id':marketplaceSellerId,'customer':customer,'totalAmount': totalAmount,'adminAmount': commissionAmount,'sellerAmount': totalPayout,'paidAmount': paidAmount,'remainingAmount': totalPayout-paidAmount, 'currencyCode':currencyId});
                        }  
                        
                    }
                })
            
            
        },
        onPageChange({ page = 1, limit = 25 }) {
            this.page = page;
            this.limit = limit;
            this.getList();
        }

        
        
    }
})