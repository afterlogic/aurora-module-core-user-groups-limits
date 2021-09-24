'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
	ModulesManager = require('%PathToCoreWebclientModule%/js/ModulesManager.js'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	CAbstractSettingsFormView = ModulesManager.run('AdminPanelWebclient', 'getAbstractSettingsFormViewClass'),
	
	Settings = require('modules/%ModuleName%/js/Settings.js')
;

/**
* @constructor
*/
function CBusinessTenantLimitsSettingsView()
{
	CAbstractSettingsFormView.call(this, Settings.ServerModuleName, 'UpdateBusinessTenantLimits');

	/* Editable fields */
	this.aliasesCount = ko.observable(0);
	this.emailAccountsCount = ko.observable(0);
	this.mailStorageQuotaMb = ko.observable(0);
	this.filesStorageQuotaMb = ko.observable(0);
	/*-- Editable fields */
}

_.extendOwn(CBusinessTenantLimitsSettingsView.prototype, CAbstractSettingsFormView.prototype);

CBusinessTenantLimitsSettingsView.prototype.ViewTemplate = '%ModuleName%_BusinessTenantLimitsSettingsView';

CBusinessTenantLimitsSettingsView.prototype.getCurrentValues = function()
{
	return [
		this.aliasesCount(),
		this.emailAccountsCount(),
		this.mailStorageQuotaMb(),
		this.filesStorageQuotaMb()
	];
};

CBusinessTenantLimitsSettingsView.prototype.getParametersForSave = function ()
{
	var oParameters = {
		'TenantId': this.iTenantId,
		'AliasesCount': Types.pInt(this.aliasesCount(), 0),
		'EmailAccountsCount': Types.pInt(this.emailAccountsCount(), 0),
		'MailStorageQuotaMb': Types.pInt(this.mailStorageQuotaMb(), 0),
		'FilesStorageQuotaMb': Types.pInt(this.filesStorageQuotaMb(), 0)
	};
	return oParameters;
};

CBusinessTenantLimitsSettingsView.prototype.setAccessLevel = function (sEntityType, iEntityId)
{
	this.iTenantId = (sEntityType === 'Tenant') ? iEntityId : 0;
	this.updateSavedState();
};

CBusinessTenantLimitsSettingsView.prototype.parse = function (iEntityId, oResult)
{
	if (iEntityId === this.iTenantId && oResult)
	{
		this.visible(!!oResult['CoreUserGroupsLimits::IsBusiness']);
		this.aliasesCount(Types.pInt(oResult['CoreUserGroupsLimits::AliasesCount'], 0));
		this.emailAccountsCount(Types.pInt(oResult['CoreUserGroupsLimits::EmailAccountsCount'], 0));
		this.mailStorageQuotaMb(Types.pInt(oResult['Mail::TenantSpaceLimitMb'], 0));
		this.filesStorageQuotaMb(Types.pInt(oResult['Files::TenantSpaceLimitMb'], 0));
	}
	else
	{
		this.visible(false);
	}
	this.updateSavedState();
};

module.exports = new CBusinessTenantLimitsSettingsView();
