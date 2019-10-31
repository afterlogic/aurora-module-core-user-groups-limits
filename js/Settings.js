'use strict';

var
	_ = require('underscore'),
	
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js')
;

module.exports = {
	ServerModuleName: '%ModuleName%',

	BannerUrl: '',
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
			this.BannerUrl = Types.pString(oAppDataSection.BannerUrl, this.BannerUrl);
			this.BannerLink = Types.pString(oAppDataSection.BannerLink, this.BannerLink);
		}
	},
};
