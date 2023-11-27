const { Module } = Shopware;

import './page/mp-config-data';
import enGB from './snippet/en-GB';
import deDE from './snippet/de-DE';

Module.register('mp-config',{
    type: 'plugin',
    title: 'wk-mp-config.label.smartBarHeading',
    snippets: {
        'en-GB': enGB,
        'de-DE': deDE
    },
    routes: {
        'settings': {
            component: 'mp-config-data',
            path: 'settings',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
    },
    settingsItem: [{
        name: 'marketplace-config',
        label: 'wk-mp-config.label.smartBarHeading',
        to: 'mp.config.settings',
        icon: 'default-object-marketing',
        group: 'plugins',
    }
]
})