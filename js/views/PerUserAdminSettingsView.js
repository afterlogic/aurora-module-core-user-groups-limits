'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	
	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
	Api = require('%PathToCoreWebclientModule%/js/Api.js'),
	App = require('%PathToCoreWebclientModule%/js/App.js'),
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),
	
	ModulesManager = require('%PathToCoreWebclientModule%/js/ModulesManager.js'),
	CAbstractSettingsFormView = ModulesManager.run('AdminPanelWebclient', 'getAbstractSettingsFormViewClass'),
	
	Cache = require('modules/CoreUserGroups/js/Cache.js'),
	Settings = require('modules/%ModuleName%/js/Settings.js'),
	
	GroupIdEnum = {
		None: 0,
		CustomEmpty: -1
	},
	oCustomGroupTemplate = {
		Id: GroupIdEnum.CustomEmpty,
		Name: TextUtils.i18n('%MODULENAME%/LABEL_CUSTOM_GROUP_NAME'),
		TenantId: 0
	}
;

/**
* @constructor of object which is used to manage user groups at the user level.
*/
function CPerUserAdminSettingsView()
{
	CAbstractSettingsFormView.call(this, Settings.UserGroupsServerModuleName);
	
	this.entityType = ko.observable('');
	
	this.iUserId = 0;
	
	this.customGroup = ko.observable(this.getCustomGroupData());
	this.currentUserGroupId = ko.observable(GroupIdEnum.None);
	this.selectedGroupId = ko.observable(GroupIdEnum.None);
	this.selectedGroup = ko.computed(function () {
		if (this.selectedGroupId() === GroupIdEnum.CustomEmpty || this.customGroup() && this.selectedGroupId() === this.customGroup().Id)
		{
			return this.customGroup();
		}
		else if (this.selectedGroupId() === GroupIdEnum.None)
		{
			return null;
		}
		return _.find(Cache.groups(), function (oGroup) {
			return oGroup.Id === this.selectedGroupId();
		}.bind(this)) || null;
	}, this);
	this.isCustomGroup = ko.computed(function () {
		return this.selectedGroup() && this.selectedGroup().TenantId === 0;
	}, this);
	
	this.groups = ko.computed(function () {
		var oGroupsOptions = _.map(Cache.groups(), function (oGroup) {
			var sText = oGroup.Name;
			if (oGroup.IsDefault)
			{
				sText += ' (' + TextUtils.i18n('ADMINPANELWEBCLIENT/LABEL_DEFAULT') + ')';
			}
			return {
				disabled: false,
				value: oGroup.Id,
				text: sText
			};
		});
		oGroupsOptions.unshift({
			disabled: true,
			value: GroupIdEnum.None,
			text: TextUtils.i18n('%MODULENAME%/LABEL_SELECT_GROUP')
		});
		if (this.customGroup())
		{
			oGroupsOptions.push({
				disabled: false,
				value: this.customGroup().Id,
				text: this.customGroup().Name
			});
		}
		return oGroupsOptions;
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
	
	this.selectedGroup.subscribe(function () {
		var oSelectedGroup = this.selectedGroup();
		if (oSelectedGroup)
		{
			this.emailSendLimitPerDay(oSelectedGroup['%ModuleName%::EmailSendLimitPerDay']);
			this.mailSignature(oSelectedGroup['%ModuleName%::MailSignature']);
			this.mailQuotaMb(oSelectedGroup['%ModuleName%::MailQuotaMb']);
			this.filesQuotaMb(oSelectedGroup['%ModuleName%::FilesQuotaMb']);
			this.allowMobileApps(oSelectedGroup['%ModuleName%::AllowMobileApps']);
			this.bannerUrlMobile(oSelectedGroup['%ModuleName%::BannerUrlMobile']);
			this.bannerUrlDesktop(oSelectedGroup['%ModuleName%::BannerUrlDesktop']);
			this.bannerLink(oSelectedGroup['%ModuleName%::BannerLink']);
			this.maxAllowedActiveAliasCount(oSelectedGroup['%ModuleName%::MaxAllowedActiveAliasCount']);
			this.aliasCreationIntervalDays(oSelectedGroup['%ModuleName%::AliasCreationIntervalDays']);
		}
	}, this);
	
	this.visible = ko.computed(function () {
		return this.entityType() === 'User' && this.groups().length > 0;
	}, this);
	
	App.subscribeEvent('ReceiveAjaxResponse::after', _.bind(function (oParams) {
		if (oParams.Request.Module === 'Core'
			&& oParams.Request.Method === 'GetUser'
			&& oParams.Request.Parameters.Id === this.iUserId
			&& oParams.Response.Result
			&& oParams.Response.Result.EntityId === this.iUserId)
		{
			this.currentUserGroupId(Types.pInt(oParams.Response.Result[Settings.UserGroupsServerModuleName + '::GroupId']));
		}
		
		if (oParams.Request.Module === Settings.UserGroupsServerModuleName
			&& oParams.Request.Method === 'AddToGroup'
			&& _.indexOf(oParams.Request.Parameters.UsersIds, this.iUserId) !== -1
			&& oParams.Response.Result)
		{
			this.currentUserGroupId(Types.pInt(oParams.Request.Parameters.GroupId));
		}
	}, this));
	
	this.currentUserGroupId.subscribe(function () {
		var oCurrentUserGroup = _.find(Cache.groups(), function (oGroup) {
			return oGroup.Id === this.currentUserGroupId();
		}.bind(this));
		
		if (this.currentUserGroupId() === 0 || oCurrentUserGroup)
		{
			this.customGroup(this.getCustomGroupData());
			this.selectedGroupId(this.currentUserGroupId());
		}
		else
		{
			var oParameters = {
				'Id': this.currentUserGroupId()
			};
			Ajax.send(
				Settings.UserGroupsServerModuleName,
				'GetGroup',
				oParameters,
				function (oResponse, oRequest) {
					if (oResponse && oResponse.Result)
					{
						this.customGroup(oResponse.Result);
						this.selectedGroupId(this.currentUserGroupId());
					}
					else
					{
						this.customGroup(this.getCustomGroupData());
						this.selectedGroupId(GroupIdEnum.None);
					}
				},
				this
			);
		}
	}, this);
}

_.extendOwn(CPerUserAdminSettingsView.prototype, CAbstractSettingsFormView.prototype);

CPerUserAdminSettingsView.prototype.ViewTemplate = '%ModuleName%_PerUserAdminSettingsView';

CPerUserAdminSettingsView.prototype.getCustomGroupData = function()
{
	var oDefaultGroup = _.find(Cache.groups(), function (oGroup) {
		return oGroup.IsDefault;
	});
	return {
		Id: oCustomGroupTemplate.Id,
		Name: oCustomGroupTemplate.Name,
		TenantId: oCustomGroupTemplate.TenantId,
		'%ModuleName%::EmailSendLimitPerDay': oDefaultGroup ? oDefaultGroup['%ModuleName%::EmailSendLimitPerDay'] : 0,
		'%ModuleName%::MailSignature': oDefaultGroup ? oDefaultGroup['%ModuleName%::MailSignature'] : '',
		'%ModuleName%::MailQuotaMb': oDefaultGroup ? oDefaultGroup['%ModuleName%::MailQuotaMb'] : 0,
		'%ModuleName%::FilesQuotaMb': oDefaultGroup ? oDefaultGroup['%ModuleName%::FilesQuotaMb'] : 0,
		'%ModuleName%::AllowMobileApps': oDefaultGroup ? oDefaultGroup['%ModuleName%::AllowMobileApps'] : false,
		'%ModuleName%::BannerUrlMobile': '',
		'%ModuleName%::BannerUrlDesktop': '',
		'%ModuleName%::BannerLink': '',
		'%ModuleName%::MaxAllowedActiveAliasCount': oDefaultGroup ? oDefaultGroup['%ModuleName%::MaxAllowedActiveAliasCount'] : 0,
		'%ModuleName%::AliasCreationIntervalDays': oDefaultGroup ? oDefaultGroup['%ModuleName%::AliasCreationIntervalDays'] : 0
	};
};

/**
 * Updates group identifier of user.
 */
CPerUserAdminSettingsView.prototype.updateUserGroup = function()
{
	if (this.selectedGroupId() === GroupIdEnum.None)
	{
		return;
	}
	
	this.isSaving(true);
	
	var
		oParameters = {
			'UserId': this.iUserId,
			'GroupId': this.selectedGroupId()
		}
	;
	if (this.isCustomGroup())
	{
		oParameters = _.extend(oParameters, {
			'%ModuleName%::EmailSendLimitPerDay': Types.pInt(this.emailSendLimitPerDay()),
			'%ModuleName%::MailSignature': this.mailSignature(),
			'%ModuleName%::MailQuotaMb': Types.pInt(this.mailQuotaMb()),
			'%ModuleName%::FilesQuotaMb': Types.pInt(this.filesQuotaMb()),
			'%ModuleName%::AllowMobileApps': this.allowMobileApps(),
			'%ModuleName%::BannerUrlMobile': this.bannerUrlMobile(),
			'%ModuleName%::BannerUrlDesktop': this.bannerUrlDesktop(),
			'%ModuleName%::BannerLink': this.bannerLink(),
			'%ModuleName%::MaxAllowedActiveAliasCount': Types.pInt(this.maxAllowedActiveAliasCount()),
			'%ModuleName%::AliasCreationIntervalDays': Types.pInt(this.aliasCreationIntervalDays())
		});
	}
	
	Ajax.send(
		Settings.UserGroupsServerModuleName,
		'UpdateUserGroup',
		oParameters,
		function (oResponse, oRequest) {
			this.isSaving(false);
			if (!oResponse.Result)
			{
				Api.showErrorByCode(oResponse, TextUtils.i18n('COREWEBCLIENT/ERROR_SAVING_SETTINGS_FAILED'));
			}
			else
			{
				Screens.showReport(TextUtils.i18n('COREWEBCLIENT/REPORT_SETTINGS_UPDATE_SUCCESS'));
			}
		},
		this
	);
};

/**
 * Sets access level for the view via entity type and entity identifier.
 * This view is visible only for User entity type.
 * 
 * @param {string} sEntityType Current entity type.
 * @param {number} iEntityId Indentificator of current intity.
 */
CPerUserAdminSettingsView.prototype.setAccessLevel = function (sEntityType, iEntityId)
{
	this.entityType(sEntityType);
	if (this.iUserId !== iEntityId)
	{
		this.iUserId = iEntityId;
	}
};

module.exports = new CPerUserAdminSettingsView();
