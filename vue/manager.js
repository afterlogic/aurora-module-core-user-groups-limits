import GroupFilterForUsers from './components/GroupFilterForUsers'

export default {
  moduleName: 'CoreUserGroupsLimits',

  requiredModules: [],

  getAdminSystemTabs () {
    return [
      {
        tabName: 'reserved-list',
        tabTitle: 'COREUSERGROUPSLIMITS.ADMIN_SETTINGS_TAB_LABEL',
        tabRouteChildren: [
          { path: 'reserved-list', component: () => import('./components/ReservedListAdminSettings') },
        ],
      },
    ]
  },

  getTenantOtherDataComponents () {
    return import('./components/EditBusinessTenant')
  },

  getFiltersForUsers () {
    return [
      GroupFilterForUsers
    ]
  },
}
