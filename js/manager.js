'use strict';

module.exports = function (oAppData) {
	var
		$ = require('jquery'),
		ko = require('knockout'),

		TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
		App = require('%PathToCoreWebclientModule%/js/App.js'),
		
		Settings = require('modules/%ModuleName%/js/Settings.js'),

		bInitialized = false
	;

	Settings.init(oAppData);

	if (App.isMobile()) {
		if (App.isUserNormalOrTenant()) {
			return {
				start: function (ModulesManager) {
					App.subscribeEvent('MailMobileWebclient::ConstructView::after', function (oParams) {
						if (ko.isSubscribable(oParams.View.selectedPanel) && !bInitialized) {

							var sHtml = '';
							sHtml += '<span class="banner" style="display: block; text-align: center;">';
							sHtml += Settings.BannerLink !== '' ? '<a href="' + Settings.BannerLink + '" target="_blank">' : '';
							sHtml += Settings.BannerUrlMobile !== '' ? '<img src="' + Settings.BannerUrlMobile + '" />' : '';
							sHtml += Settings.ShowTitle !== '' ? '<span class="link">' + TextUtils.i18n('%MODULENAME%/ADMIN_SETTINGS_TAB_LABEL') + '</span>' : '';
							sHtml += Settings.BannerLink !== '' ? '</a>' : '';
							sHtml += '</span>';

							var oBannerEl = $(sHtml);
							oParams.View.selectedPanel.subscribe(function (value) {
								if (!bInitialized && Enums.MobilePanel.Groups === value) {
									$("#auroraContent").find('.MailLayout').find('.panel-left').prepend(oBannerEl);
									bInitialized = true;
								}
							});
						}
					});
				}
			};
		}
	}
	else
	{
		var
			fGetHeaderItem = function () {
				return {
					item: require('modules/%ModuleName%/js/views/HeaderItemView.js'),
					name: ''
				};
			},
			fStart = function (ModulesManager) {
				ModulesManager.run('AdminPanelWebclient', 'registerAdminPanelTab', [
					function (resolve) {
						require.ensure(
							['modules/%ModuleName%/js/views/AdminSettingsView.js'],
							function () {
								resolve(require('modules/%ModuleName%/js/views/AdminSettingsView.js'));
							},
							'admin-bundle'
						);
					},
					Settings.HashReservedList,
					TextUtils.i18n('%MODULENAME%/ADMIN_SETTINGS_TAB_LABEL')
				]);
				
				ModulesManager.run('AdminPanelWebclient', 'changeAdminPanelEntityData', [{
					Type: 'Group',
					EditView: require('modules/%ModuleName%/js/views/EditGroupView.js')
				}]);
				ModulesManager.run('AdminPanelWebclient', 'registerAdminPanelTab', [
					function(resolve) {
						require.ensure(
							['modules/%ModuleName%/js/views/PerUserAdminSettingsView.js'],
							function() {
								resolve(require('modules/%ModuleName%/js/views/PerUserAdminSettingsView.js'));
							},
							"admin-bundle"
						);
					},
					'user-groups-user',
					TextUtils.i18n('COREUSERGROUPS/LABEL_SETTINGS_TAB_USERGROUPS')
				]);
				ModulesManager.run('AdminPanelWebclient', 'registerAdminPanelTabSection', [
						function () {
							return require('modules/%ModuleName%/js/views/LimitsHintAdminSettingsView.js');
						},
						'files'
					]
				);
				ModulesManager.run('AdminPanelWebclient', 'registerAdminPanelTabSection', [
						function () {
							return require('modules/%ModuleName%/js/views/LimitsHintAdminSettingsView.js');
						},
						'mail-quota'
					]
				);

				ModulesManager.run('AdminPanelWebclient', 'registerAdminPanelTabSection', [
					function () {
						return require('modules/%ModuleName%/js/views/BusinessTenantLimitsSettingsView.js');
					},
					'common'
				]);
			},
			oResult = {}
		;

		switch (App.getUserRole()) {
			case Enums.UserRole.SuperAdmin:
				oResult.start = fStart;
				break;
			case Enums.UserRole.TenantAdmin:
			case Enums.UserRole.NormalUser:
				oResult.getHeaderItem = fGetHeaderItem;
				break;
			default:
				break;
		}

		return oResult;
	}

	return null;
};
