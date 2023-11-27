const { Module } = Shopware;
import './page/mp-order-list';
import enGB from './snippet/en-GB';
import deDE from './snippet/de-DE';

Module.register('mp-order', {
    type: 'plugin',
    title: 'mp-order.general.mainMenuItemList',
    description: 'mp-order.general.descriptionTextModule',
    snippets: {
        'en-GB': enGB,
        'de-DE': deDE
    },
    favicon: 'icon-module-orders.png',

    routes: {
        'list': {
            component: 'mp-order-list',
            path: 'list',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    },

    settingsItem: [
        {
            name: 'marketplace-orders',
            label: 'mp-order.general.mainMenuItemList',
            to: 'mp.order.list',
            icon: 'default-object-marketing',
            group: 'plugins',
        }
    ]
});
