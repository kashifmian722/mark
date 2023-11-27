import './init/api-service.init';
import './module/sellers';
import './module/products';
import './module/orders';
import './module/transaction';
import './module/transaction-history';
import './module/mp-mail-template';
import './module/mp-config';
import './view/sw-order-detail-base';
import template from './extension/sw-order-detail/sw-order-detail.html.twig';



const { Criteria } = Shopware.Data;
const { Component } = Shopware;



Component.override('sw-order-detail',{
    
    template,
    inject:[
        'repositoryFactory',
        'WkMarketplaceApiService'
    ],
        data(){
            return {
                marketplaceOrderRepository: null,
                mpOrderHistory: null,
                total: 0,
                orderOptions: [],
                orderStateId: null
            }
        },
        created() {
            this.stateRepository  = this.repositoryFactory.create('state_machine');
            this.stateMachineRepository = this.repositoryFactory.create('state_machine_state');
            this.marketplaceOrderRepository = this.repositoryFactory.create('marketplace_order');
            this.getMarketplaceLineItemStatus();
            this.getOrderStatus();
            

        },
        computed: {
            getMpLineItemColumns(){
              return [
                  {
                    property: 'label',
                    dataIndex: 'label',
                    label: this.$t('mp-order.detail.columnProductName'),
                    allowResize: false,
                    primary: true,
                    inlineEdit: true,
                    width: '200px'
                },{
                    property: 'status',
                    dataIndex: 'status',
                    label: this.$t('mp-order.detail.columnItemStatus'),
                    allowResize: false,
                    primary: true,
                    inlineEdit: true,
                    width: '200px'
                }
                  
                  
              ]
            }
        },
        methods: {
            getOrderStatus() {
                const stateCriteria = new Criteria();
                stateCriteria.addFilter(Criteria.equals('technicalName', 'order.state'));
                this.stateRepository.search(stateCriteria, Shopware.Context.api).then((result)=>{
                    this.orderStateId = result[0].id;
                    const stateTranslationCriteria = new Criteria();
                     stateTranslationCriteria.addFilter(Criteria.equals('stateMachineId', this.orderStateId));
                    this.stateMachineRepository.search(stateTranslationCriteria, Shopware.Context.api).then((result)=>{
                        this.orderOptions = result
                    })
                })
            },
            updateMpStatus(mpOrderId, event){
                this.WkMarketplaceApiService.updateOrderStatus(mpOrderId,event).then((response)=>{
                    if(response){
                        this.createNotificationSuccess({
                            title: this.$tc('mp-order.detail.successTitle'),
                            message: this.$tc('mp-order.detail.successMessage')
                        });
                    }
                })
            },
            
            getMarketplaceLineItemStatus() {
               const criteria = new Criteria();
               criteria.addFilter(Criteria.equals('orderId', this.orderId));
               criteria.addAssociation('order')
               criteria.addAssociation('order_line_item')
               this.marketplaceOrderRepository.search(criteria, Shopware.Context.api).then((result)=>{
                
                this.total = result.total
                this.mpOrderHistory = result;
               })
            }
        }
    
})

// Component.override('sw-order-line-items-grid', {

//     inject: [
//         'repositoryFactory'
//     ],
//     data(){
//         return {
//             canceledLineItemId: [],
//             cancelStateId: null,
//         }
//     },
//     created() {
//         this.marketplaceOrderRepository = this.repositoryFactory.create('marketplace_order');
//         this.stateMachineRepository = this.repositoryFactory.create('state_machine_state');
//         this.getStateId();
       
//     },
   
//     methods: {
//         getStateId() {
//             const stateCriteria = new Criteria();
//             stateCriteria.addFilter(Criteria.equals('technicalName', 'cancelled'));
//             stateCriteria.addAssociation('stateMachine')
//             stateCriteria.addFilter(Criteria.equals('stateMachine.technicalName', 'order.state'))
//             this.stateMachineRepository.search(stateCriteria, Shopware.Context.api).then((result)=>{
//                 this.cancelStateId = result[0].id;
//                 this.getOrder();
//             })
//         },
//         getOrder() {
//             const criteria = new Criteria();
//             criteria.addFilter(Criteria.equals('orderStatus', this.cancelStateId));
//             criteria.addFilter(Criteria.equals('orderId', this.order.id));
//             this.orderLineItems.forEach((lineItem,iteration, object)=>{
//             this.marketplaceOrderRepository.search(criteria, Shopware.Context.api).then((result) => {
//                 if(result.total > 0){
//                     result.forEach((item)=>{
//                        if(item.orderLineItemId == lineItem.id){
//                         this.orderLineItems[iteration].label = this.orderLineItems[iteration].label+ '  '+   item.state_machine_state.translated.name;
                      
//                         this.order.amountTotal = this.order.amountTotal - lineItem.totalPrice;
//                         this.order.positionPrice = this.order.positionPrice - lineItem.totalPrice
//                         this.order.amountNet = this.order.amountNet - (lineItem.totalPrice-lineItem.price.calculatedTaxes[0].tax);
//                         let taxRate = lineItem.price.calculatedTaxes[0].taxRate;
//                         this.order.price.calculatedTaxes.forEach((taxes, index)=>{
//                             if(taxes.taxRate == taxRate){
//                                 this.order.price.calculatedTaxes[index].tax = taxes.tax - lineItem.price.calculatedTaxes[0].tax;
//                                 this.order.price.calculatedTaxes[index].price = taxes.price - lineItem.price.calculatedTaxes[0].price
//                             }
//                         })
//                        }
//                     })
//                 }
//                 });
                
//             })
            
//         },
//     }

// })
