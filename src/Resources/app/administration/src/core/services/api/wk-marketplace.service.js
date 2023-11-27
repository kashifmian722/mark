const { ApiService}  = Shopware.Classes;

class WkMarketplaceApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'wk.marketplace') {
        super(httpClient, loginService, apiEndpoint);
    }

    saveProductStatus(productId, active) {
        let apiRoute = `product/${productId}`;

        return this.httpClient.patch(
            apiRoute, { active },
            {
                headers: this.getBasicHeaders()
            }
        ).then(response => {
            return ApiService.handleResponse(response);
        });
    }
    approveSellers(sellerId, active) {
        let apiRoute = `${this.getApiBasePath()}/seller/approve`;
        return this.httpClient.post(
            apiRoute, {sellerId:sellerId, active:active},
            {
                headers: this.getBasicHeaders()
            }).then(response => {
                return ApiService.handleResponse(response);
            });
    }
    bulkSaveProductStatus(productIds, active) {
        let apiRoute = `${this.getApiBasePath()}/product/status`;
        return this.httpClient.post(
            apiRoute, {productId:productIds, active:active},
            {
                headers: this.getBasicHeaders()
            }).then(response => {
                return ApiService.handleResponse(response);
            });
    }
    deleteShopwareProduct(productId) {
        let apiRoute = `${this.getApiBasePath()}/delete/shopware/products`;
        return this.httpClient.post(
            apiRoute, {productId: productId},
            {
                headers: this.getBasicHeaders()
            }
        ).then(response => {
            return ApiService.handleResponse(response)
        });
    }
    bulkDeleteShopwareProduct(productIds) {
        let apiRoute = `${this.getApiBasePath()}/bulk/delete/shopware/products`;
        return this.httpClient.post(
            apiRoute, {productIds: productIds},
            {
                headers: this.getBasicHeaders()
            }
        ).then(response => {
            return ApiService.handleResponse(response)
        });
    }
    saveTransaction(commissionId,message) {
        let apiRoute = `${this.getApiBasePath()}/transaction`;
       
        return this.httpClient.post(
            apiRoute, {commissionId:commissionId,message:message},
            {
                headers: this.getBasicHeaders()
            }).then(response => {
                return ApiService.handleResponse(response);
            });
        };
    updateOrderStatus(mpOrderId, stateId){
        let apiRoute =`${this.getApiBasePath()}/update/order`;
        return this.httpClient.post(
            apiRoute, {mpOrderId:mpOrderId,stateId:stateId},
            {
                headers: this.getBasicHeaders()
            }).then(response => {
                return ApiService.handleResponse(response);
            });
    };
    saveConfig(config,saleschannelId) {
        let apiRoute =`${this.getApiBasePath()}/save/config`;
        return this.httpClient.post(
            apiRoute, {config: config,saleschannelId: saleschannelId},
            {
                headers: this.getBasicHeaders()
            }).then(response => {
                return ApiService.handleResponse(response);
            });
    }
    createShippingLabel(orderNumber,productId,quantity,sellerId, productPrice) {
        let apiRoute = `${this.getApiBasePath()}/create/shipping/label`;
        return this.httpClient.post(
            apiRoute, {orderNumber: orderNumber, productId: productId, quantity:quantity, sellerId:sellerId, productPrice:productPrice},
            {
                headers: this.getBasicHeaders()
            }).then(response => {
                return ApiService.handleResponse(response);
            });
    }
}

export default WkMarketplaceApiService;
