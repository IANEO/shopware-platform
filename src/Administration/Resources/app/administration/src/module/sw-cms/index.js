import defaultSearchConfiguration from './default-search-configuration';
import './service/cms.service';
import './service/cmsDataResolver.service';
import './state/cms-page.state';
import './mixin/sw-cms-element.mixin';
import './mixin/sw-cms-state.mixin';
import './blocks';
import './elements';
import './component';
import './acl';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-cms-list', () => import('./page/sw-cms-list'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-detail', () => import('./page/sw-cms-detail'));
/**
 * @private
 * @package content
 */
Shopware.Component.extend('sw-cms-create', 'sw-cms-detail', () => import('./page/sw-cms-create'));

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Module.register('sw-cms', {
    type: 'core',
    name: 'cms',
    title: 'sw-cms.general.mainMenuItemGeneral',
    description: 'The module for creating content.',
    color: '#ff68b4',
    icon: 'regular-content',
    favicon: 'icon-module-content.png',
    entity: 'cms_page',

    routes: {
        index: {
            component: 'sw-cms-list',
            path: 'index',
            meta: {
                privilege: 'cms.viewer',
            },
        },
        detail: {
            component: 'sw-cms-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.cms.index',
                privilege: 'cms.viewer',
            },
        },
        create: {
            component: 'sw-cms-create',
            path: 'create/:type/:id',
            meta: {
                parentPath: 'sw.cms.index',
                privilege: 'cms.creator',
            },
        },
    },

    navigation: [{
        id: 'sw-content',
        label: 'global.sw-admin-menu.navigation.mainMenuItemContent',
        color: '#ff68b4',
        icon: 'regular-content',
        position: 50,
    }, {
        id: 'sw-cms',
        label: 'sw-cms.general.mainMenuItemGeneral',
        color: '#ff68b4',
        path: 'sw.cms.index',
        icon: 'regular-content',
        position: 10,
        parent: 'sw-content',
        privilege: 'cms.viewer',
    }],

    defaultSearchConfiguration,
});
