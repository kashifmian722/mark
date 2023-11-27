const { Module } = Shopware;

import './page/mp-transaction-list';
import './page/mp-transaction-detail';

import enGB from './snippet/en-GB';
import deDE from './snippet/de-DE';

Module.register('mp-transaction', {
    type: 'plugin',
    name: 'Marketplace',
    title: 'mp-transaction.general.mainMenuItemList',
    description: 'mp-transaction.general.descriptionTextModule',
    favicon: 'icon-module-customers.png',

    snippets: {
        'en-GB': enGB,
        'de-DE': deDE
    },

    routes: {
        'list': {
            component: 'mp-transaction-list',
            path: 'list',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        'detail': {
            component: 'mp-transaction-detail',
            path: 'detail/:id'
        }
        
    },

    settingsItem: [
         {
            name: 'marketplace-transaction',
            label: 'mp-transaction.general.mainMenuItemList',
            to: 'mp.transaction.list',
            icon: 'default-object-marketing',
            group: 'plugins',
        }
    ]
});
