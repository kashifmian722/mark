
const { Application } = Shopware; 
import WkMarketplaceApiService from '../core/services/api/wk-marketplace.service';

Application.addServiceProvider('WkMarketplaceApiService', (container) => {
    const initContainer = Application.getContainer('init');
    return new WkMarketplaceApiService(initContainer.httpClient, container.loginService);
});
