'use strict';

var
	_ = require('underscore'),

	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js')
;

module.exports = {
	ServerModuleName: '%ModuleName%',
	HashReservedList: 'reserved-list',

	BannerUrlMobile: '',
	BannerUrlDesktop: '',
	BannerLink: '',
	ShowTitle: false,

	/**
	 * Initializes settings from AppData object sections.
	 *
	 * @param {Object} oAppData Object contained modules settings.
	 */
	init: function (oAppData)
	{
		var oAppDataSection = oAppData[this.ServerModuleName];

		if (!_.isEmpty(oAppDataSection))
		{
			this.BannerUrlMobile = Types.pString(oAppDataSection.BannerUrlMobile, this.BannerUrlMobile);
			this.BannerUrlDesktop = Types.pString(oAppDataSection.BannerUrlDesktop, this.BannerUrlDesktop);
			this.BannerLink = Types.pString(oAppDataSection.BannerLink, this.BannerLink);
		}
	},
};
