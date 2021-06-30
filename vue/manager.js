
export default {
  moduleName: 'CoreUserGroupsLimits',

  requiredModules: [],

  getAdminSystemTabs () {
    return [
      {
        tabName: 'reserved-list',
        title: 'COREUSERGROUPSLIMITS.ADMIN_SETTINGS_TAB_LABEL',
        component () {
          return import('src/../../../CoreUserGroupsLimits/vue/components/ReservedListAdminSettings')
        },
      },
    ]
  },
}