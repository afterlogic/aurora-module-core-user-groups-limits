'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	
	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
	App = require('%PathToCoreWebclientModule%/js/App.js'),
	ModulesManager = require('%PathToCoreWebclientModule%/js/ModulesManager.js'),
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),
	
	Settings = require('modules/%ModuleName%/js/Settings.js')
;

/**
 * @constructor
 */
function CCache()
{
	this.selectedTenantId = ModulesManager.run('AdminPanelWebclient', 'getKoSelectedTenantId');
	this.groupsByTenants = ko.observable({});
	if (_.isFunction(this.selectedTenantId))
	{
		this.selectedTenantId.subscribe(function () {
			if (typeof this.groupsByTenants()[this.selectedTenantId()] === 'undefined')
			{
				Ajax.send(Settings.UserGroupsServerModuleName, 'GetGroups', { TenantId: this.selectedTenantId() });
			}
		}, this);
	}
	this.groups = ko.computed(function () {
		var aGroups = _.isFunction(this.selectedTenantId) ? this.groupsByTenants()[this.selectedTenantId()] : [];
		return _.isArray(aGroups) ? aGroups : [];
	}, this);
	
	App.subscribeEvent('AdminPanelWebclient::ConstructView::after', function (oParams) {
		if (oParams.Name === 'CSettingsView' && Types.isPositiveNumber(oParams.View.selectedTenant().Id))
		{
			Ajax.send(Settings.UserGroupsServerModuleName, 'GetGroups', { TenantId: oParams.View.selectedTenant().Id });
		}
	}.bind(this));
	App.subscribeEvent('ReceiveAjaxResponse::after', this.onAjaxResponse.bind(this));
}

/**
 * Only Cache object knows if groups are empty or not received yet.
 * So error will be shown as soon as groups will be received from server if they are empty.
 * @returns Boolean
 */
CCache.prototype.showErrorIfGroupsEmpty = function ()
{
	var
		bGroupsEmptyOrUndefined = true,
		fShowErrorIfGroupsEmpty = function () {
			if (this.groups().length === 0)
			{
				Screens.showError(TextUtils.i18n('%MODULENAME%/ERROR_ADD_GROUP_FIRST'));
			}
			else
			{
				bGroupsEmptyOrUndefined = false;
			}
		}.bind(this)
	;
	
	if (_.isFunction(this.selectedTenantId))
	{
		if (typeof this.groupsByTenants()[this.selectedTenantId()] === 'undefined')
		{
			var fSubscription = this.groupsByTenants.subscribe(function () {
				fShowErrorIfGroupsEmpty();
				fSubscription.dispose();
				fSubscription = undefined;
			});
		}
		else
		{
			fShowErrorIfGroupsEmpty();
		}
	}
	
	return bGroupsEmptyOrUndefined;
};

CCache.prototype.onAjaxResponse = function (oParams)
{
	var
		sModule = oParams.Response.Module,
		sMethod = oParams.Response.Method
	;
	
	if (sModule === Settings.UserGroupsServerModuleName && sMethod === 'GetGroups')
	{
		var
			sSearch = Types.pString(oParams.Request.Parameters.Search),
			iOffset = Types.pInt(oParams.Request.Parameters.Offset)
		;
		if (sSearch === '' && iOffset === 0)
		{
			var
				iTenantId = oParams.Request.Parameters.TenantId,
				aGroups = oParams.Response.Result && _.isArray(oParams.Response.Result.Items) ? oParams.Response.Result.Items : []
			;

			_.each(aGroups, function (oGroup) {
				oGroup.Id = Types.pInt(oGroup.Id);
			});

			this.groupsByTenants()[iTenantId] = aGroups;
			this.groupsByTenants.valueHasMutated();
		}
	}
};

module.exports = new CCache();
