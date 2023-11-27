const { Module } = Shopware;
import './page/mail-template-list';
import './page/mail-template-detail';

import enGB from './snippet/en-GB';
import deDE from './snippet/de-DE';

Module.register('mp-mail-template', {
    type: 'plugin',
    title: 'mp-mail.general.mainMenuItemList',
    description: 'mp-mail.general.descriptionTextModule',
    snippets: {
        'en-GB': enGB,
        'de-De': deDE
    },

    routes: {
        'list': {
            component: 'mail-template-list',
            path: 'list',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        'detail': {
            component: 'mail-template-detail',
            path: 'detail/:id'
        }
    },

    settingsItem: [
        {
            name: 'marketplace-mail-template',
            label: 'mp-mail.general.mainMenuItemList',
            to: 'mp.mail.template.list',
            group: 'plugins',
            icon: 'default-object-marketing'
        }
    ]
});
