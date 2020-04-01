'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),

	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
	Api = require('%PathToCoreWebclientModule%/js/Api.js'),
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	ModulesManager = require('%PathToCoreWebclientModule%/js/ModulesManager.js'),
	CAbstractSettingsFormView = ModulesManager.run('AdminPanelWebclient', 'getAbstractSettingsFormViewClass'),

	Settings = require('modules/%ModuleName%/js/Settings.js')
;

/**
* @constructor
*/
function СAdminSettingsView() {
	CAbstractSettingsFormView.call(this, Settings.ServerModuleName);

	this.accountName = ko.observable('');
	this.reservedNames = ko.observableArray([]);
	this.selectedReservedNames = ko.observableArray([]);
}

_.extendOwn(СAdminSettingsView.prototype, CAbstractSettingsFormView.prototype);

СAdminSettingsView.prototype.ViewTemplate = '%ModuleName%_AdminSettingsView';

/**
 * Runs after routing to this view.
 */
СAdminSettingsView.prototype.onRouteChild = function ()
{
	this.accountName('');
	this.selectedReservedNames([]);
	this.requestReservedNames();
};

/**
 * Sends request to create a new reserved name.
 */
СAdminSettingsView.prototype.addReservedName = function ()
{
	if (this.accountName() === '')
	{
		Screens.showError(TextUtils.i18n('%MODULENAME%/ERROR_EMPTY_RESERVED_NAME'));
	}
	else
	{
		Ajax.send(Settings.ServerModuleName,
			'AddNewReservedName',
			{
				'AccountName': this.accountName()
			},
			function (oResponse) {
				if (oResponse.Result)
				{
					this.accountName('');
					this.requestReservedNames();
				}
				else
				{
					Api.showErrorByCode(oResponse, TextUtils.i18n('%MODULENAME%/ERROR_ADD_NEW_RESERVED_NAME'));
				}
			},
			this
		);
	}
};

/**
 * Sends request to delete selected reserved names.
 */
СAdminSettingsView.prototype.deleteReservedNames = function ()
{
	if (this.selectedReservedNames().length === 0)
	{
		Screens.showError(TextUtils.i18n('%MODULENAME%/ERROR_EMPTY_RESERVED_NAMES'));
		return;
	}

	Ajax.send(Settings.ServerModuleName,
		'DeleteReservedNames',
		{
			'ReservedNames': this.selectedReservedNames()
		},
		function (oResponse) {
			if (oResponse.Result)
			{
				this.selectedReservedNames([]);
				this.requestReservedNames();
			}
			else
			{
				Api.showErrorByCode(
					oResponse,
					TextUtils.i18n(
						'%MODULENAME%/ERROR_DELETE_RESERVED_NAMES_PLURAL',
						{},
						null,
						this.selectedReservedNames().length
					)
				);
			}
		},
		this
	);
};

/**
 * Requests reserved names.
 */
СAdminSettingsView.prototype.requestReservedNames = function ()
{
	this.reservedNames([]);
	Ajax.send(
		Settings.ServerModuleName,
		'GetReservedNames',
		{},
		function (oResponse) {
			if (oResponse.Result)
			{
				this.reservedNames(oResponse.Result);
			}
		}, this
	);
};

/**
 * Sets access level for the view via entity type and entity identifier.
 * This view is visible only for Tenant entity type.
 *
 * @param {string} sEntityType Current entity type.
 * @param {number} iEntityId Indentificator of current intity.
 */
СAdminSettingsView.prototype.setAccessLevel = function (sEntityType, iEntityId)
{
	this.visible(sEntityType === '');
};

module.exports = new СAdminSettingsView();
