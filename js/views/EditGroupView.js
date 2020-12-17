'use strict';

var
	_ = require('underscore'),
	$ = require('jquery'),
	ko = require('knockout'),
	
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	
	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
	Api = require('%PathToCoreWebclientModule%/js/Api.js'),
	App = require('%PathToCoreWebclientModule%/js/App.js'),
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),
	
	Popups = require('%PathToCoreWebclientModule%/js/Popups.js'),
	ConfirmPopup = require('%PathToCoreWebclientModule%/js/popups/ConfirmPopup.js'),
	
	Cache = require('modules/CoreUserGroups/js/Cache.js'),
	Settings = require('modules/%ModuleName%/js/Settings.js')
;

/**
 * @constructor of object that allows to create/edit group.
 */
function CEditGroupView()
{
	this.id = ko.observable(0);
	this.createMode = ko.computed(function () {
		return this.id() === 0;
	}, this);
	this.tenantId = ko.observable(0);
	this.name = ko.observable('');
	this.isDefault = ko.observable(false);
	this.allowDelete = ko.computed(function () {
		return !this.isDefault();
	}, this);
	this.emailSendLimitPerDay = ko.observable(0);
	this.mailSignature = ko.observable('');
	this.mailQuotaMb = ko.observable(0);
	this.filesQuotaMb = ko.observable(0);
	this.allowMobileApps = ko.observable(false);
	this.bannerUrlMobile = ko.observable('');
	this.bannerUrlDesktop = ko.observable('');
	this.bannerLink = ko.observable('');
	this.maxAllowedActiveAliasCount = ko.observable(0);
	this.aliasCreationIntervalDays = ko.observable(0);
	
	this.sHeading = TextUtils.i18n('%MODULENAME%/HEADING_CREATE_GROUP');
	this.sActionCreate = TextUtils.i18n('COREWEBCLIENT/ACTION_CREATE');
	this.sActionCreateInProgress = TextUtils.i18n('COREWEBCLIENT/ACTION_CREATE_IN_PROGRESS');
	
	App.broadcastEvent('%ModuleName%::ConstructView::after', {'Name': this.ViewConstructorName, 'View': this});
}

CEditGroupView.prototype.ViewTemplate = '%ModuleName%_EditGroupView';
CEditGroupView.prototype.ViewConstructorName = 'CEditGroupView';

/**
 * Returns array with all settings values wich is used for indicating if there were changes on the page.
 * @returns {Array} Array with all settings values.
 */
CEditGroupView.prototype.getCurrentValues = function ()
{
	return [
		this.id(),
		this.name(),
		this.emailSendLimitPerDay(),
		this.mailSignature(),
		this.mailQuotaMb(),
		this.filesQuotaMb(),
		this.allowMobileApps(),
		this.bannerUrlMobile(),
		this.bannerUrlDesktop(),
		this.bannerLink(),
		this.maxAllowedActiveAliasCount(),
		this.aliasCreationIntervalDays()
	];
};

/**
 * Clears all fields values.
 */
CEditGroupView.prototype.clearFields = function ()
{
	this.id(0);
	this.tenantId(0);
	this.name('');
	this.isDefault(false);
	this.emailSendLimitPerDay(0);
	this.mailSignature('');
	this.mailQuotaMb(0);
	this.filesQuotaMb(0);
	this.allowMobileApps(false);
	this.bannerUrlMobile('');
	this.bannerUrlDesktop('');
	this.bannerLink('');
	this.maxAllowedActiveAliasCount(0);
	this.aliasCreationIntervalDays(0);
};

/**
 * Parses entity to edit.
 * @param {int} iEntityId Entity identifier.
 * @param {object} oResult Entity data from server.
 */
CEditGroupView.prototype.parse = function (iEntityId, oResult)
{
	if (oResult)
	{
		this.id(iEntityId);
		this.tenantId(Types.pInt(oResult.TenantId, 0));
		this.name(Types.pString(oResult.Name, ''));
		this.isDefault(Types.pBool(oResult.IsDefault, false));
		this.emailSendLimitPerDay(Types.pInt(oResult['%ModuleName%::EmailSendLimitPerDay'], 0));
		this.mailSignature(Types.pString(oResult['%ModuleName%::MailSignature'], ''));
		this.mailQuotaMb(Types.pInt(oResult['%ModuleName%::MailQuotaMb'], 0));
		this.filesQuotaMb(Types.pInt(oResult['%ModuleName%::FilesQuotaMb'], 0));
		this.allowMobileApps(Types.pBool(oResult['%ModuleName%::AllowMobileApps'], false));
		this.bannerUrlMobile(Types.pString(oResult['%ModuleName%::BannerUrlMobile'], ''));
		this.bannerUrlDesktop(Types.pString(oResult['%ModuleName%::BannerUrlDesktop'], ''));
		this.bannerLink(Types.pString(oResult['%ModuleName%::BannerLink'], ''));
		this.maxAllowedActiveAliasCount(Types.pInt(oResult['%ModuleName%::MaxAllowedActiveAliasCount'], 0));
		this.aliasCreationIntervalDays(Types.pInt(oResult['%ModuleName%::AliasCreationIntervalDays'], 0));
	}
	else
	{
		this.clearFields();
	}
};

/**
 * Checks if data is valid before its saving.
 * @returns {boolean}
 */
CEditGroupView.prototype.isValidSaveData = function ()
{
	var
		bValidUserName = $.trim(this.name()) !== ''
	;
	if (!bValidUserName)
	{
		Screens.showError(TextUtils.i18n('%MODULENAME%/ERROR_GROUP_NAME_EMPTY'));
		return false;
	}
	return true;
};

/**
 * Obtains parameters for saving on the server.
 * @returns {object}
 */
CEditGroupView.prototype.getParametersForSave = function ()
{
	return {
		'Id': this.id(),
		'Name': $.trim(this.name()),
		'%ModuleName%::EmailSendLimitPerDay': this.emailSendLimitPerDay(),
		'%ModuleName%::MailSignature': this.mailSignature(),
		'%ModuleName%::MailQuotaMb': this.mailQuotaMb(),
		'%ModuleName%::FilesQuotaMb': this.filesQuotaMb(),
		'%ModuleName%::AllowMobileApps': this.allowMobileApps(),
		'%ModuleName%::BannerUrlMobile': this.bannerUrlMobile(),
		'%ModuleName%::BannerUrlDesktop': this.bannerUrlDesktop(),
		'%ModuleName%::BannerLink': this.bannerLink(),
		'%ModuleName%::MaxAllowedActiveAliasCount': this.maxAllowedActiveAliasCount(),
		'%ModuleName%::AliasCreationIntervalDays': this.aliasCreationIntervalDays()
	};
};

/**
 * Saves entity by pressing enter in the field input.
 * @param {array} aParents
 * @param {object} oRoot
 */
CEditGroupView.prototype.saveEntity = function (aParents, oRoot)
{
	_.each(aParents, function (oParent) {
		if (_.isFunction(oParent.createEntity))
		{
			oParent.createEntity();
		}
		else if (_.isFunction(oParent.save))
		{
			oParent.save(oRoot);
		}
	});
};

/**
 * Asks for confirmation to set the group as default.
 * @param {array} aParents
 */
CEditGroupView.prototype.askSetDefault = function (aParents)
{
	Popups.showPopup(ConfirmPopup, [TextUtils.i18n('%MODULENAME%/CONFIRM_SET_DEFAULT'), 
		function (bOk) {
			if (bOk)
			{
				this.setDefault(aParents);
			}
		}.bind(this)
	]);
};

/**
 * Sets the group as default. After that requests group list.
 * @param {array} aParents
 */
CEditGroupView.prototype.setDefault = function (aParents)
{
	Ajax.send(Settings.UserGroupsServerModuleName,
		'ChangeDefaultGroup',
		{
			'TenantId': this.tenantId(),
			'DefaultGroupId': this.id()
		},
		function (oResponse) {
			if (oResponse.Result)
			{
				_.each(aParents, function (oParent) {
					this.isDefault(true);
					if (oParent && _.isFunction(oParent.currentEntitiesView) && _.isFunction(oParent.currentEntitiesView().requestEntities))
					{
						oParent.currentEntitiesView().requestEntities();
					}
				}.bind(this));
			}
			else
			{
				Api.showErrorByCode(oResponse, TextUtils.i18n('%MODULENAME%/ERROR_ADD_NEW_RESERVED_NAME'));
			}
		},
		this
	);
};

module.exports = new CEditGroupView();
