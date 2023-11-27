const { Module } = Shopware;

import './page/transaction-history-list';
import enGB from './snippet/en-GB';
import deDE from './snippet/de-DE';

Module.register('transaction-history', {
    type: 'plugin',
    name: 'Marketplace',
    title: 'mp-transaction-history.general.mainMenuItemList',
    description: 'mp-transaction-history.general.descriptionTextModule',
    favicon: 'icon-module-customers.png',

    snippets: {
        'en-GB': enGB,
        'de-DE': deDE
    },

    routes: {
        'list': {
            component: 'transaction-history-list',
            path: 'list',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
        
    },

    settingsItem: [
         {
            name: 'marketplace-transaction-history-list',
            label: 'mp-transaction-history.general.mainMenuItemList',
            to: 'transaction.history.list',
            icon: 'default-object-marketing',
            group: 'plugins',
        }
    ]
});
