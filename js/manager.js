'use strict';

module.exports = function (oAppData) {
	var
		$ = require('jquery'),
		ko = require('knockout'),

		App = require('%PathToCoreWebclientModule%/js/App.js'),

		Settings = require('modules/%ModuleName%/js/Settings.js'),

		bInitialized = false
	;

	Settings.init(oAppData);
	
	if (App.isUserNormalOrTenant())
	{
		if (App.isMobile())
		{
			return {
				start: function (ModulesManager) {
					App.subscribeEvent('MailMobileWebclient::ConstructView::after', function (oParams) {
						if (ko.isSubscribable(oParams.View.selectedPanel) && !bInitialized) {

							var sHtml = '';
								sHtml += Settings.BannerLink !== '' ? `<a href="${Settings.BannerLink}" target="_blank" class="item banner" style="max-width: 100%;">` : '<span class="item banner" style="max-width: 100%;">';
								sHtml += Settings.BannerUrl !== '' ? `<img src="${Settings.BannerUrl}" />` : '';
								sHtml += Settings.ShowTitle !== '' ? `<span class="link" data-bind="i18n: {'key': '%MODULENAME%/UPRGADE_NOW'}"></span>` : '';
								sHtml += Settings.BannerLink !== '' ? '</a>' : '</span>';

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
		} else {
			return {
				getHeaderItem: function () {
					return {
						item: require('modules/%ModuleName%/js/views/HeaderItemView.js'),
						name: 'asdasd'
					};
				}
			};
		};
	}

	return null;
};
