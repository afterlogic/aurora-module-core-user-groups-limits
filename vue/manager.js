
export default {
  moduleName: 'CoreUserGroupsLimits',

  requiredModules: [],

  getAdminSystemTabs () {
    return [
      {
        tabName: 'reserved-list',
        title: 'COREUSERGROUPSLIMITS.ADMIN_SETTINGS_TAB_LABEL',
        component () {
          return import('./components/ReservedListAdminSettings')
        },
      },
    ]
  },

  getTenantOtherDataComponents () {
    return import('./components/EditBusinessTenant')
  },

  async getFiltersForUsers () {
    return [
      await import('./components/GroupFilterForUsers')
    ]
  },
}
