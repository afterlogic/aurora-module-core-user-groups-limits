'use strict';

var
	_ = require('underscore'),
	
	ModulesManager = require('%PathToCoreWebclientModule%/js/ModulesManager.js'),
	CAbstractSettingsFormView = ModulesManager.run('AdminPanelWebclient', 'getAbstractSettingsFormViewClass')
;

/**
* @constructor
*/
function CLimitsHintAdminSettingsView()
{
	CAbstractSettingsFormView.call(this);
}

_.extendOwn(CLimitsHintAdminSettingsView.prototype, CAbstractSettingsFormView.prototype);

CLimitsHintAdminSettingsView.prototype.ViewTemplate = '%ModuleName%_LimitsHintAdminSettingsView';

/**
 * Sets access level for the view via entity type and entity identifier.
 * This view is visible only for empty entity type.
 * 
 * @param {string} sEntityType Current entity type.
 * @param {number} iEntityId Indentificator of current intity.
 */
CLimitsHintAdminSettingsView.prototype.setAccessLevel = function (sEntityType, iEntityId)
{
	this.visible(sEntityType === 'User');
};

module.exports = new CLimitsHintAdminSettingsView();
