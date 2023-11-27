const { Module } = Shopware;

import './page/mp-product-list';
import './page/mp-product-assign';
import enGB from './snippet/en-GB';
import deDE from './snippet/de-DE';

Module.register('mp-product', {
    type: 'plugin',
    title: 'mp-product.general.mainMenuItemGeneral',
    description: 'mp-product.general.descriptionTextModule',
    favicon: 'icon-module-products.png',

    snippets: {
        'en-GB': enGB,
        'de-DE': deDE
    },

    routes: {
        'list': {
            component: 'mp-product-list',
            path: 'list',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        'assign': {
            component: 'mp-product-assign',
            path: 'assign',
            meta: {
                parentPath: 'mp.product.list'
            }
        }
    },

    settingsItem: [{
            name: 'marketplace-products',
            label: 'mp-product.general.mainMenuItemGeneral',
            to: 'mp.product.list',
            icon: 'default-object-marketing',
            group: 'plugins',
        }
    ]
});
