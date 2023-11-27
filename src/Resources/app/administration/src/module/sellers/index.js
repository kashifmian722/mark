const { Module } = Shopware;

import './page/mp-seller-list';
import './page/mp-seller-detail';
import  './page/mp-seller-create';
import enGB from './snippet/en-GB';
import deDE from './snippet/de-DE';

Module.register('mp-seller', {
    type: 'plugin',
    name: 'Marketplace',
    title: 'mp-seller.general.mainMenuItemList',
    description: 'mp-seller.general.descriptionTextModule',
    favicon: 'icon-module-customers.png',

    snippets: {
        'en-GB': enGB,
        'de-DE': deDE
    },

    routes: {
        'list': {
            component: 'mp-seller-list',
            path: 'list',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        'detail': {
            component: 'mp-seller-detail',
            path: 'detail/:id',
            meta: {
                parentPath : 'mp.seller.list'
            }
            
        },
        'create': {
            component: 'mp-seller-create',
            path: 'create',
            meta: {
                parentPath : 'mp.seller.list'
            }

        }
    },

    settingsItem: [
         {
            name: 'marketplace-sellers',
            label: 'mp-seller.general.mainMenuItemList',
            to: 'mp.seller.list',
            icon: 'default-object-marketing',
            group: 'plugins',
        }
    ]
});
