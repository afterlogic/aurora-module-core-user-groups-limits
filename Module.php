<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\CoreUserGroupsLimits;

use Aurora\Modules\Core\Models\User;

/**
 * Provides user groups.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2020, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	public function init()
	{
		$this->subscribeEvent('Mail::SendMessage::before', array($this, 'onBeforeSendMessage'));
		$this->subscribeEvent('Mail::SendMessage::after', array($this, 'onAfterSendMessage'));
		$this->subscribeEvent('Mail::CreateAccount::after', array($this, 'onAfterCreateAccount'));

		$this->subscribeEvent('CoreUserGroups::RemoveUsersFromGroup::after', array($this, 'onAfterRemoveUsersFromGroup'));
		$this->subscribeEvent('CoreUserGroups::AddToGroup::after', array($this, 'onAfterAddToGroup'));
		$this->subscribeEvent('CoreUserGroups::UpdateUserGroup::before', array($this, 'onBeforeUpdateUserGroup'));
		$this->subscribeEvent('CoreUserGroups::UpdateUserGroup::after', array($this, 'onAfterUpdateUserGroup'));
		$this->subscribeEvent('CoreUserGroups::GetGroups::before', array($this, 'onBeforeGetGroups'));
		$this->subscribeEvent('CoreUserGroups::CreateGroup::before', array($this, 'onBeforeCreateGroup'));
		$this->subscribeEvent('CoreUserGroups::CreateGroup::after', array($this, 'onAfterCreateGroup'));

		$this->subscribeEvent('CpanelIntegrator::CreateAlias::before', array($this, 'onBeforeCreateAlias'));
		$this->subscribeEvent('CpanelIntegrator::AddNewAlias::after', array($this, 'onAfterCreateAlias'));
		$this->subscribeEvent('CpanelIntegrator::GetSettings::after', array($this, 'onAfterGetSettings'));

		$this->subscribeEvent('PersonalFiles::GetUserSpaceLimitMb', array($this, 'onGetUserSpaceLimitMb'));

		$this->subscribeEvent('System::RunEntry::before', array($this, 'onBeforeRunEntry'));
//		$this->subscribeEvent('Core::Login::after', array($this, 'onAfterLogin'), 10);

		$this->subscribeEvent('Core::Authenticate::after', array($this, 'onAfterAuthenticate'), 10);

		$this->subscribeEvent('Files::GetSettingsForEntity::after', array($this, 'onAfterGetSettingsForEntity'));

		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oAuthenticatedUser instanceof \Aurora\Modules\Core\Models\User)
		{
			if ($oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin)
			{
				$this->aAdditionalEntityFieldsToEdit[] = [
					'DisplayName' => $this->i18N('LABEL_ITS_BUSINESS_TENANT'),
					'Entity' => 'Tenant',
					'FieldName' => self::GetName() . '::IsBusiness',
					'FieldType' => 'bool',
					'Hint' => $this->i18N('HINT_ITS_BUSINESS_TENANT_HTML'),
					'EnableOnCreate' => true,
					'EnableOnEdit' => false
				];
			}
			if ($oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin || $oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::TenantAdmin)
			{
				$this->aAdditionalEntityFieldsToEdit[] = [
					'DisplayName' => $this->i18N('LABEL_ENABLE_GROUPWARE'),
					'Entity' => 'Tenant',
					'FieldName' => self::GetName() . '::EnableGroupware',
					'FieldType' => 'bool',
					'Hint' => '',
					'EnableOnCreate' => $oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin,
					'EnableOnEdit' => $oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin
				];
			}
		}

		$this->subscribeEvent('Core::CreateUser::before', array($this, 'onBeforeCreateUser'));
		$this->subscribeEvent('AdminPanelWebclient::CreateTenant::after', array($this, 'onAfterAdminPanelCreateTenant')); /** @deprecated since version 8.3.7 **/
		$this->subscribeEvent('Core::CreateTenant::after', array($this, 'onAfterCreateTenant'));
		$this->subscribeEvent('AdminPanelWebclient::UpdateEntity::after', array($this, 'onAfterAdminPanelUpdateTenant')); /** @deprecated since version 8.3.7 **/
		$this->subscribeEvent('Core::UpdateTenant::after', array($this, 'onAfterUpdateTenant'));
		$this->subscribeEvent('Core::Tenant::ToResponseArray', array($this, 'onTenantToResponseArray'));
		$this->subscribeEvent('Core::CreateUser::after', array($this, 'onAfterCreateUser'));
		$this->subscribeEvent('Mail::CreateAccount::before', array($this, 'onBeforeCreateAccount'));
		$this->subscribeEvent('CpanelIntegrator::AddNewAlias::before', array($this, 'onBeforeAddNewAlias'));
		$this->subscribeEvent('Mail::IsEmailAllowedForCreation::after', array($this, 'onAfterIsEmailAllowedForCreation'));
		$this->subscribeEvent('CoreUserGroups::Group::ToResponseArray', array($this, 'onGroupToResponseArray'));
		$this->subscribeEvent('CoreUserGroups::UpdateGroup::after', array($this, 'onAfterUpdateGroup'));

		$aBusinessTenantLimits = $this->getConfig('BusinessTenantLimits', []);
		if (is_array($aBusinessTenantLimits) && count($aBusinessTenantLimits) > 0 && is_array($aBusinessTenantLimits[0]))
		{
			$aBusinessTenantLimits = $aBusinessTenantLimits[0];
		}
		else
		{
			$aBusinessTenantLimits = [];
		}
		// \Aurora\Modules\Core\Classes\Tenant::extend(
		// 	self::GetName(),
		// 	[
		// 		'IsBusiness' => array('bool', false),
		// 		'AliasesCount' => array('int', isset($aBusinessTenantLimits['AliasesCount']) ? $aBusinessTenantLimits['AliasesCount'] : 0),
		// 		'EmailAccountsCount' => array('int', isset($aBusinessTenantLimits['EmailAccountsCount']) ? $aBusinessTenantLimits['EmailAccountsCount'] : 0),
		// 	]
		// );

		// \Aurora\Modules\Core\Classes\User::extend(
		// 	self::GetName(),
		// 	[
		// 		'EmailSentCount' => array('int', 0),
		// 		'EmailSentDate' => array('datetime', date('Y-m-d'), true),
		// 		'TotalAliasCount' => ['int', 0], //count of active and deleted aliases
		// 		'LastAliasCreationDate' => ['datetime', date('Y-m-d H:i:s', 0), true] //time of last created alias (still active or already deleted) for the account
		// 	]
		// );

		// \Aurora\Modules\CoreUserGroups\Classes\Group::extend(
		// 	self::GetName(),
		// 	[
		// 		'DataSavedInDb' => array('bool', false),
		// 		'EmailSendLimitPerDay' => array('int', 0),
		// 		'MailSignature' => array('string', ''),
		// 		'MailQuotaMb' => array('int', 0),
		// 		'FilesQuotaMb' => array('int', 0),
		// 		'AllowMobileApps' => array('bool', false),
		// 		'BannerUrlMobile' => array('string', ''),
		// 		'BannerUrlDesktop' => array('string', ''),
		// 		'BannerLink' => array('string', ''),
		// 		'MaxAllowedActiveAliasCount' => array('int', 0),
		// 		'AliasCreationIntervalDays' => array('int', 0),
		// 	]
		// );
	}

	private function checkIfEmailReserved($sEmail)
	{
		$sAccountName = \MailSo\Base\Utils::GetAccountNameFromEmail($sEmail);
		$sDomain = \MailSo\Base\Utils::GetDomainFromEmail($sEmail);
		$aDomainObjects = \Aurora\System\Api::GetModuleDecorator('MailDomains')->getDomainsManager()->getFullDomainsList()->toArray();
		$aDomains = array_map(function ($oDomain) {
			return $oDomain->Name;
		}, $aDomainObjects);
		$aReservedAccountNames = $this->getConfig('ReservedList', []);
		if (
			is_array($aDomains)
			&& is_array($aReservedAccountNames)
			&& in_array($sAccountName, $aReservedAccountNames)
			&& in_array($sDomain, $aDomains)
		)
		{
			return true;
		}

		return false;

	}

	protected function getGroupSetting($iUserId, $sSettingName)
	{
		$aAllSettings = null;
		$oCoreUserGroupsDecorator = \Aurora\Modules\CoreUserGroups\Module::Decorator();
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserUnchecked($iUserId);
		if ($oCoreUserGroupsDecorator && $oUser instanceof \Aurora\Modules\Core\Models\User)
		{
			$oGroup = $oCoreUserGroupsDecorator->GetGroup($oUser->{'CoreUserGroups::GroupId'});
			if (!($oGroup instanceof \Aurora\Modules\CoreUserGroups\Models\Group))
			{
				$oGroup = $oCoreUserGroupsDecorator->GetDefaultGroup($oUser->IdTenant);
			}
			$aAllSettings = $this->getAllSettingsOfGroup($oGroup);
		}
		return is_array($aAllSettings) && isset($aAllSettings[$sSettingName]) ? $aAllSettings[$sSettingName] : null;
	}

	/**
	 * Obtains all settings for group.
	 * If group is specified and its settings are saved in DB, then obtains settings from DB.
	 * If group is specified and its settings are not saved in DB, then obtains settings from config file by group name.
	 * If group is not specified or there are no settings for the group name in config file, then obtains settings from config file for the first group in the list.
	 * @param \Aurora\Modules\CoreUserGroups\Models\Group|null $oGroup
	 * @return array|null
	 */
	protected function getAllSettingsOfGroup($oGroup)
	{
		$aAllSettings = null;

		if ($oGroup instanceof \Aurora\Modules\CoreUserGroups\Models\Group && $oGroup->{self::GetName() . '::DataSavedInDb'})
		{
			$aAllSettings = [
				'EmailSendLimitPerDay' => $oGroup->{self::GetName() . '::EmailSendLimitPerDay'},
				'MailSignature' => $oGroup->{self::GetName() . '::MailSignature'},
				'MailQuotaMb' => $oGroup->{self::GetName() . '::MailQuotaMb'},
				'FilesQuotaMb' => $oGroup->{self::GetName() . '::FilesQuotaMb'},
				'AllowMobileApps' => $oGroup->{self::GetName() . '::AllowMobileApps'},
				'BannerUrlMobile' => $oGroup->{self::GetName() . '::BannerUrlMobile'},
				'BannerUrlDesktop' => $oGroup->{self::GetName() . '::BannerUrlDesktop'},
				'BannerLink' => $oGroup->{self::GetName() . '::BannerLink'},
				'MaxAllowedActiveAliasCount' => $oGroup->{self::GetName() . '::MaxAllowedActiveAliasCount'},
				'AliasCreationIntervalDays' => $oGroup->{self::GetName() . '::AliasCreationIntervalDays'},
			];
		}
		else
		{
			$aGroupsLimits = $this->getConfig('GroupsLimits', '');
			$iIndex = false;
			if ($oGroup instanceof \Aurora\Modules\CoreUserGroups\Models\Group)
			{
				$iIndex = array_search($oGroup->Name, array_column($aGroupsLimits, 'GroupName'));
			}
			if ($iIndex === false)
			{
				$iIndex = 0;
			}
			if (isset($aGroupsLimits[$iIndex]) && is_array($aGroupsLimits[$iIndex]))
			{
				$aAllSettings = $aGroupsLimits[$iIndex];
			}
		}

		return $aAllSettings;
	}

	protected function isTodayEmailSentDate($oUser)
	{
		return $oUser instanceof \Aurora\Modules\Core\Models\User
				&& ($oUser->{self::GetName() . '::EmailSentDate'} === date('Y-m-d')
				|| $oUser->{self::GetName() . '::EmailSentDate'} === date('Y-m-d') . ' 00:00:00');
	}

	protected function isUserNotFromBusinessTenant($oUser)
	{
		if ($oUser instanceof \Aurora\Modules\Core\Models\User)
		{
			$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantUnchecked($oUser->IdTenant);
			if ($oTenant instanceof \Aurora\Modules\Core\Models\Tenant && !$oTenant->{self::GetName() . '::IsBusiness'})
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onBeforeSendMessage(&$aArgs, &$mResult)
	{
		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();

		if ($this->isUserNotFromBusinessTenant($oAuthenticatedUser))
		{
			if ($this->isTodayEmailSentDate($oAuthenticatedUser))
			{
				$iEmailSendLimitPerDay = $this->getGroupSetting($oAuthenticatedUser->Id, 'EmailSendLimitPerDay');

				if ($oAuthenticatedUser->{self::GetName() . '::EmailSentCount'} >= $iEmailSendLimitPerDay)
				{
					throw new \Exception($this->i18N('ERROR_USER_SENT_MESSAGES_LIMIT', ['COUNT' => $iEmailSendLimitPerDay]));
				}
			}

			$sMailSignature = $this->getGroupSetting($oAuthenticatedUser->Id, 'MailSignature');
			if (is_string($sMailSignature) && $sMailSignature !== '')
			{
				$aArgs['Text'] .= ($aArgs['IsHtml'] ? '<br />' : "\r\n") . $sMailSignature;
			}
		}
	}

	/**
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterSendMessage(&$aArgs, &$mResult)
	{
		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($mResult === true && $this->isUserNotFromBusinessTenant($oAuthenticatedUser))
		{
			if ($this->isTodayEmailSentDate($oAuthenticatedUser))
			{
				$oAuthenticatedUser->setExtendedProp(self::GetName() . '::EmailSentCount', $oAuthenticatedUser->{self::GetName() . '::EmailSentCount'} + 1);
			}
			else
			{
				$oAuthenticatedUser->setExtendedProp(self::GetName() . '::EmailSentCount', 1);
			}
			$oAuthenticatedUser->setExtendedProp(self::GetName() . '::EmailSentDate', date('Y-m-d'));
			$oAuthenticatedUser->save();
		}
	}

	public function onGetUserSpaceLimitMb(&$aArgs, &$mResult)
	{
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserUnchecked($aArgs['UserId']);
		if ($mResult === true && $this->isUserNotFromBusinessTenant($oUser))
		{
			$iFilesQuotaMb = $this->getGroupSetting($aArgs['UserId'], 'FilesQuotaMb');
			if (is_int($iFilesQuotaMb))
			{
				$mResult = $iFilesQuotaMb;
			}
		}
	}

	/**
	 * Applies capabilities for users removed from groups.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterRemoveUsersFromGroup(&$aArgs, &$mResult)
	{
		if ($mResult)
		{
			$this->setUserListCapabilities($aArgs['UsersIds']);
		}
	}

	/**
	 * Applies capabilities for users removed from groups.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterAddToGroup(&$aArgs, &$mResult)
	{
		if ($mResult)
		{
			$this->setUserListCapabilities($aArgs['UsersIds']);
		}
	}

	public function onBeforeUpdateUserGroup(&$aArgs, &$mResult)
	{
		$oUser = isset($aArgs['UserId']) ? \Aurora\Modules\Core\Module::Decorator()->GetUserUnchecked($aArgs['UserId']) : null;
		if ($oUser instanceof \Aurora\Modules\Core\Models\User)
		{
			$oCustomUserGroup = null;
			$iUserGroupId = $oUser->{'CoreUserGroups::GroupId'};
			if ($iUserGroupId > 0 && $iUserGroupId !== $aArgs['GroupId'])
			{
				$oGroup = \Aurora\Modules\CoreUserGroups\Module::Decorator()->GetGroup($iUserGroupId);
				if ($oGroup instanceof \Aurora\Modules\CoreUserGroups\Models\Group && $oGroup->TenantId === 0) // custom group
				{
					// delete group if it was custom and no longer belong to user
					\Aurora\Modules\CoreUserGroups\Module::Decorator()->DeleteGroups($oGroup->TenantId, [$oGroup->Id]);
				}
			}

			if ($iUserGroupId > 0 && $iUserGroupId === $aArgs['GroupId'])
			{
				$oGroup = \Aurora\Modules\CoreUserGroups\Module::Decorator()->GetGroup($iUserGroupId);
				if ($oGroup instanceof \Aurora\Modules\CoreUserGroups\Models\Group && $oGroup->TenantId === 0) // custom group
				{
					$oCustomUserGroup = $oGroup;
				}
			}

			if ($aArgs['GroupId'] === -1) // new custom group
			{
				$aArgs['GroupId'] = \Aurora\Modules\CoreUserGroups\Module::Decorator()->CreateGroup(0, $this->i18N('LABEL_CUSTOM_GROUP_NAME'));
				$oGroup = \Aurora\Modules\CoreUserGroups\Module::Decorator()->GetGroup($aArgs['GroupId']);
				if ($oGroup instanceof \Aurora\Modules\CoreUserGroups\Models\Group && $oGroup->TenantId === 0) // custom group
				{
					$oCustomUserGroup = $oGroup;
				}
			}

			if ($oCustomUserGroup !== null)
			{
				$oCustomUserGroup->setExtendedProp(self::GetName() . '::DataSavedInDb', true);
				$oCustomUserGroup->setExtendedProp(self::GetName() . '::EmailSendLimitPerDay', $aArgs[self::GetName() . '::EmailSendLimitPerDay']);
				$oCustomUserGroup->setExtendedProp(self::GetName() . '::MailSignature', $aArgs[self::GetName() . '::MailSignature']);
				$oCustomUserGroup->setExtendedProp(self::GetName() . '::MailQuotaMb', $aArgs[self::GetName() . '::MailQuotaMb']);
				$oCustomUserGroup->setExtendedProp(self::GetName() . '::FilesQuotaMb', $aArgs[self::GetName() . '::FilesQuotaMb']);
				$oCustomUserGroup->setExtendedProp(self::GetName() . '::AllowMobileApps', $aArgs[self::GetName() . '::AllowMobileApps']);
				$oCustomUserGroup->setExtendedProp(self::GetName() . '::MaxAllowedActiveAliasCount', $aArgs[self::GetName() . '::MaxAllowedActiveAliasCount']);
				$oCustomUserGroup->setExtendedProp(self::GetName() . '::AliasCreationIntervalDays', $aArgs[self::GetName() . '::AliasCreationIntervalDays']);
				$oCustomUserGroup->save();
			}
		}
	}

	/**
	 * Applies capabilities for user.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterUpdateUserGroup($aArgs, &$mResult)
	{
		if ($mResult)
		{
			$this->setUserListCapabilities([$aArgs['UserId']]);
		}
	}

	public function onAfterGetSettings($aArgs, &$mResult)
	{
		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::TenantAdmin)
		{
			$oTenant = \Aurora\Modules\Core\Module::Decorator()->getTenantsManager()->getTenantById($oAuthenticatedUser->IdTenant);
			if ($oTenant && !$oTenant->{self::GetName() . '::IsBusiness'})
			{
				$mResult['AllowAliases'] = false;
			}
		}
		else if ($oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::NormalUser)
		{
			$oTenant = \Aurora\Modules\Core\Module::Decorator()->getTenantsManager()->getTenantById($oAuthenticatedUser->IdTenant);
			if ($oTenant && $oTenant->{self::GetName() . '::IsBusiness'})
			{
				$mResult['AllowAliases'] = false;
			}
		}
	}

	public function onBeforeCreateAlias($aArgs, &$mResult)
	{
		$iTenantId = $aArgs['TenantId'];
		$oTenant = \Aurora\Modules\Core\Module::Decorator()->getTenantsManager()->getTenantById($iTenantId);
		if ($oTenant && $oTenant->{self::GetName() . '::IsBusiness'})
		{//Business tenant
			$iAliasesCountLimit = $this->getBusinessTenantLimits($oTenant, 'AliasesCount');
			if (is_array($aArgs['DomainAliases']) && count($aArgs['DomainAliases']) >= $iAliasesCountLimit)
			{
				throw new \Exception($this->i18N('ERROR_BUSINESS_TENANT_ALIASES_LIMIT_PLURAL', ['COUNT' => $iAliasesCountLimit], $iAliasesCountLimit));
			}
		}
		else
		{//not Business tenant
			$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserUnchecked($aArgs['UserId']);
			$iMaxAllowedActiveAliasCount = $this->getGroupSetting($oUser->Id, 'MaxAllowedActiveAliasCount');
			//how many days must pass since the user is allowed to create an alias again (once the user hit MaxAllowedActiveAliasCount limit)
			$iAliasCreationIntervalDays = $this->getGroupSetting($oUser->Id, 'AliasCreationIntervalDays');
			$bLimitedExceeded = true;
			$iActualAliasesCount = is_array($aArgs['UserAliases']) ? count($aArgs['UserAliases']) : 0;
			$dLastAliasCreationDatel = new \DateTime($oUser->{self::GetName() . '::LastAliasCreationDate'}, new \DateTimeZone('UTC'));
			$dCurrentDate = new \DateTime('now', new \DateTimeZone('UTC'));
			if ($iActualAliasesCount < $iMaxAllowedActiveAliasCount)
			{
				if ($oUser->{self::GetName() . '::TotalAliasCount'} < $iMaxAllowedActiveAliasCount)
				{
					$bLimitedExceeded = false;
				}
				else if ($dLastAliasCreationDatel->modify("+{$iAliasCreationIntervalDays} day") < $dCurrentDate)
				{
					$bLimitedExceeded = false;
				}
			}
			if ($bLimitedExceeded)
			{
				throw new \Exception($this->i18N('ERROR_USER_ALIASES_LIMIT', ['COUNT' => $iMaxAllowedActiveAliasCount]));
			}
		}
	}

	public function onAfterCreateAlias($aArgs, &$mResult)
	{
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserUnchecked($aArgs['UserId']);
		if ($this->isUserNotFromBusinessTenant($oUser))
		{
			$oCpanelIntegratorDecorator = \Aurora\System\Api::GetModuleDecorator('CpanelIntegrator');
			$aGetAliasesResult = $oCpanelIntegratorDecorator ? $oCpanelIntegratorDecorator->GetAliases($oUser->Id) : [];
			$iActualAliasesCount = isset($aGetAliasesResult['Aliases']) ? count($aGetAliasesResult['Aliases']) : 0;
			$oUser->setExtendedProp(self::GetName() . '::TotalAliasCount', $iActualAliasesCount);
			$dCurrentDate = new \DateTime('now', new \DateTimeZone('UTC'));
			$oUser->setExtendedProp(self::GetName() . '::LastAliasCreationDate', $dCurrentDate->format('Y-m-d H:i:s'));
			\Aurora\System\Managers\Eav::getInstance()->updateEntity($oUser);
		}
	}

	public function onBeforeGetGroups($aArgs, &$mResult)
	{
		$iTenantId = $aArgs['TenantId'];
		$oTenant = \Aurora\Modules\Core\Module::Decorator()->getTenantsManager()->getTenantById($iTenantId);
		if ($oTenant && $oTenant->{self::GetName() . '::IsBusiness'})
		{
			throw new \Exception($this->i18N('ERROR_BUSINESS_TENANT_NOT_ALLOWED_HAVE_GROUPS'));
		}
	}

	public function onBeforeCreateGroup(&$aArgs, &$mResult)
	{
		$iTenantId = $aArgs['TenantId'];
		$oTenant = \Aurora\Modules\Core\Module::Decorator()->getTenantsManager()->getTenantById($iTenantId);
		if ($oTenant && $oTenant->{self::GetName() . '::IsBusiness'})
		{
			throw new \Exception($this->i18N('ERROR_BUSINESS_TENANT_NOT_ALLOWED_HAVE_GROUPS'));
		}
	}

	public function onAfterCreateGroup($aArgs, &$mResult)
	{
		if (isset($aArgs['TenantId']) && is_int($mResult))
		{
			$oGroup = \Aurora\Modules\CoreUserGroups\Module::Decorator()->GetGroup($mResult);
			$oDefaultGroup = \Aurora\Modules\CoreUserGroups\Module::Decorator()->GetDefaultGroup($aArgs['TenantId']);
			$aAllSettings = $this->getAllSettingsOfGroup($oDefaultGroup);
			if ($oDefaultGroup instanceof \Aurora\Modules\CoreUserGroups\Models\Group && $oGroup instanceof \Aurora\Modules\CoreUserGroups\Models\Group)
			{
				$oGroup->setExtendedProp(self::GetName() . '::DataSavedInDb', true);
				$oGroup->setExtendedProp(self::GetName() . '::EmailSendLimitPerDay', $aAllSettings['EmailSendLimitPerDay']);
				$oGroup->setExtendedProp(self::GetName() . '::MailSignature', $aAllSettings['MailSignature']);
				$oGroup->setExtendedProp(self::GetName() . '::MailQuotaMb', $aAllSettings['MailQuotaMb']);
				$oGroup->setExtendedProp(self::GetName() . '::FilesQuotaMb', $aAllSettings['FilesQuotaMb']);
				$oGroup->setExtendedProp(self::GetName() . '::AllowMobileApps', $aAllSettings['AllowMobileApps']);
				if ($oGroup->TenantId !== 0) // not custom group
				{
					$oGroup->setExtendedProp(self::GetName() . '::BannerUrlMobile', $aAllSettings['BannerUrlMobile']);
					$oGroup->setExtendedProp(self::GetName() . '::BannerUrlDesktop', $aAllSettings['BannerUrlDesktop']);
					$oGroup->setExtendedProp(self::GetName() . '::BannerLink', $aAllSettings['BannerLink']);
				}
				$oGroup->setExtendedProp(self::GetName() . '::MaxAllowedActiveAliasCount', $aAllSettings['MaxAllowedActiveAliasCount']);
				$oGroup->setExtendedProp(self::GetName() . '::AliasCreationIntervalDays', $aAllSettings['AliasCreationIntervalDays']);
				$oGroup->save();
			}
		}
	}

	/**
	 * Applies capabilities for users.
	 * @param array $aUsersIds
	 */
	protected function setUserListCapabilities($aUsersIds)
	{
		$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
		$aUsers = [];
		if ($oCoreDecorator && !empty($aUsersIds))
		{
			$aUsers = User::whereIn('Id', $aUsersIds)->get();
		}

		foreach ($aUsers as $oUser)
		{
			$this->setUserCapabilities($oUser);
		}
	}

	/**
	 * Applies capabilities for user.
	 * @param \Aurora\Modules\Core\Models\User $oUser
	 */
	protected function setUserCapabilities($oUser)
	{
		if ($this->isUserNotFromBusinessTenant($oUser))
		{
			$iMailQuotaMb = (int) $this->getGroupSetting($oUser->Id, 'MailQuotaMb');
			\Aurora\Modules\Mail\Module::Decorator()->UpdateEntitySpaceLimits('User', $oUser->Id, $oUser->IdTenant, null, $iMailQuotaMb);

			$oFilesDecorator = \Aurora\Modules\Files\Module::Decorator();
			$iFilesQuotaMb = $this->getGroupSetting($oUser->Id, 'FilesQuotaMb');
			if ($oFilesDecorator && is_int($iFilesQuotaMb))
			{
				$oFilesDecorator->UpdateUserSpaceLimit($oUser->Id, $iFilesQuotaMb);
			}
		}
	}

	public function GetGroupwareState($TenantId)
	{
		$bState = false;
		$oAuthenticatedUser = \Aurora\Api::getAuthenticatedUser();
		if ($oAuthenticatedUser instanceof \Aurora\Modules\Core\Models\User && ($oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin || ($oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::TenantAdmin && $oAuthenticatedUser->IdTenant === $TenantId)))
		{
			$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantUnchecked($TenantId);
			if ($oTenant instanceof \Aurora\Modules\Core\Models\Tenant)
			{
				$aDisabledModules = $oTenant->getDisabledModules();
				if (count($aDisabledModules) === 0)
				{
					$bState = true;
				}
			}
		}

		return $bState;
	}

	public function UpdateGroupwareState($TenantId, $EnableGroupware = false)
	{
		$bResult = false;
		$oAuthenticatedUser = \Aurora\Api::getAuthenticatedUser();
		if ($oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin || ($oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::TenantAdmin && $oAuthenticatedUser->IdTenant === $TenantId))
		{
			$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantUnchecked($TenantId);
			if ($oTenant instanceof \Aurora\Modules\Core\Models\Tenant)
			{
				if ($EnableGroupware)
				{
					$oTenant->clearDisabledModules();
				}
				else
				{
					$aGroupwareModules = $this->getConfig('GroupwareModules');
					if (is_array($aGroupwareModules) && count($aGroupwareModules) > 0)
					{
						$oTenant->disableModules($aGroupwareModules);
						$bResult = true;
					}
				}
			}
		}

		return $bResult;
	}

	public function onBeforeRunEntry(&$aArgs, &$mResult)
	{
		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oAuthenticatedUser instanceof \Aurora\Modules\Core\Models\User
				&& $oAuthenticatedUser->isNormalOrTenant()
				&& $this->isUserNotFromBusinessTenant($oAuthenticatedUser) && isset($aArgs['EntryName']) && strtolower($aArgs['EntryName']) === 'api')
		{
			$sXClientHeader = (string) \MailSo\Base\Http::SingletonInstance()->GetHeader('X-Client');
			$bAllowMobileApps = $this->getGroupSetting($oAuthenticatedUser->Id, 'AllowMobileApps');
			if (strtolower($sXClientHeader) !== 'webclient' && (!is_bool($bAllowMobileApps) || !$bAllowMobileApps))
			{
				$mResult = \Aurora\System\Managers\Response::GetJsonFromObject(
					'Json',
					\Aurora\System\Managers\Response::ExceptionResponse(
						'RunEntry',
						new \Aurora\System\Exceptions\ApiException(
							\Aurora\System\Notifications::AccessDenied,
							null,
							$this->i18N('ERROR_USER_MOBILE_ACCESS_LIMIT'),
							[],
							$this
						)
					)
				);

				return true;
			}
		}
	}

	public function onAfterAuthenticate(&$aArgs, &$mResult)
	{
		if ($mResult && is_array($mResult) && isset($mResult['token']))
		{
			$oUser = \Aurora\System\Api::getUserById((int) $mResult['id']);
			if ($oUser instanceof \Aurora\Modules\Core\Models\User && $oUser->isNormalOrTenant()
				&& $this->isUserNotFromBusinessTenant($oUser))
			{
				$sXClientHeader = (string) \MailSo\Base\Http::SingletonInstance()->GetHeader('X-Client');
				\Aurora\Api::skipCheckUserRole(true);
				$bAllowMobileApps = $this->getGroupSetting($oUser->Id, 'AllowMobileApps');
				\Aurora\Api::skipCheckUserRole(false);
				if (strtolower($sXClientHeader) !== 'webclient' && (!is_bool($bAllowMobileApps) || !$bAllowMobileApps))
				{
					throw new \Aurora\System\Exceptions\ApiException(
						\Aurora\System\Notifications::AccessDenied,
						null,
						$this->i18N('ERROR_USER_MOBILE_ACCESS_LIMIT'),
						[],
						$this
					);

					return true;
				}
			}
		}
	}

	public function onAfterLogin(&$aArgs, &$mResult)
	{
		if($mResult && isset($mResult['AuthToken'])) {
			$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
			if ($oAuthenticatedUser instanceof \Aurora\Modules\Core\Models\User
					&& $oAuthenticatedUser->isNormalOrTenant()
					&& $this->isUserNotFromBusinessTenant($oAuthenticatedUser)
					// && isset($aArgs['EntryName'])
					// && strtolower($aArgs['EntryName']) === 'api'
				)
			{
				$sXClientHeader = (string) \MailSo\Base\Http::SingletonInstance()->GetHeader('X-Client');
				$bAllowMobileApps = $this->getGroupSetting($oAuthenticatedUser->Id, 'AllowMobileApps');
				if (strtolower($sXClientHeader) !== 'webclient' && (!is_bool($bAllowMobileApps) || !$bAllowMobileApps))
				{
					throw new \Aurora\System\Exceptions\ApiException(
						\Aurora\System\Notifications::AccessDenied,
						null,
						$this->i18N('ERROR_USER_MOBILE_ACCESS_LIMIT'),
						[],
						$this
					);

					return true;
				}
			}
		}
	}

	public function onTenantToResponseArray($aArgs, &$mResult)
	{
		$oTenant = $aArgs['Tenant'];
		if ($oTenant instanceof \Aurora\Modules\Core\Models\Tenant && is_array($mResult))
		{
			$mResult[self::GetName() . '::IsBusiness'] = $oTenant->{self::GetName() . '::IsBusiness'};
			$mResult[self::GetName() . '::EnableGroupware'] = $this->GetGroupwareState($oTenant->Id);
			$mResult[self::GetName() . '::AliasesCount'] = $oTenant->{self::GetName() . '::AliasesCount'};
			$mResult[self::GetName() . '::EmailAccountsCount'] = $oTenant->{self::GetName() . '::EmailAccountsCount'};
			$mResult['Mail::TenantSpaceLimitMb'] = $oTenant->{'Mail::TenantSpaceLimitMb'};
			$mResult['Files::TenantSpaceLimitMb'] = $oTenant->{'Files::TenantSpaceLimitMb'};
		}
	}

	public function onGroupToResponseArray($aArgs, &$mResult)
	{
		if (is_array($mResult))
		{
			$oGroup = isset($aArgs['Group']) ? $aArgs['Group'] : null;
			$aAllSettings = $this->getAllSettingsOfGroup($oGroup);
			$mResult[self::GetName() . '::EmailSendLimitPerDay'] = $aAllSettings['EmailSendLimitPerDay'];
			$mResult[self::GetName() . '::MailSignature'] = $aAllSettings['MailSignature'];
			$mResult[self::GetName() . '::MailQuotaMb'] = $aAllSettings['MailQuotaMb'];
			$mResult[self::GetName() . '::FilesQuotaMb'] = $aAllSettings['FilesQuotaMb'];
			$mResult[self::GetName() . '::AllowMobileApps'] = $aAllSettings['AllowMobileApps'];
			$mResult[self::GetName() . '::BannerUrlMobile'] = $aAllSettings['BannerUrlMobile'];
			$mResult[self::GetName() . '::BannerUrlDesktop'] = $aAllSettings['BannerUrlDesktop'];
			$mResult[self::GetName() . '::BannerLink'] = $aAllSettings['BannerLink'];
			$mResult[self::GetName() . '::MaxAllowedActiveAliasCount'] = $aAllSettings['MaxAllowedActiveAliasCount'];
			$mResult[self::GetName() . '::AliasCreationIntervalDays'] = $aAllSettings['AliasCreationIntervalDays'];
		}
	}

	protected function getBusinessTenantLimits($oTenant, $sSettingName)
	{
		if ($oTenant && $oTenant->{self::GetName() . '::IsBusiness'})
		{
			return $oTenant->{self::GetName() . '::' . $sSettingName};
		}
		return $this->getBusinessTenantLimitsFromConfig($sSettingName);
	}

	protected function getBusinessTenantLimitsFromConfig($sSettingName)
	{
		$aBusinessTenantLimitsConfig = $this->getConfig('BusinessTenantLimits', []);
		$aBusinessTenantLimits = is_array($aBusinessTenantLimitsConfig) && count($aBusinessTenantLimitsConfig) > 0 ? $aBusinessTenantLimitsConfig[0] : [];
		return is_array($aBusinessTenantLimitsConfig) && isset($aBusinessTenantLimits[$sSettingName]) ? $aBusinessTenantLimits[$sSettingName] : null;
	}

	public function onBeforeCreateUser($aArgs, &$mResult)
	{
		$iTenantId = $aArgs['TenantId'];
		$oTenant = \Aurora\Modules\Core\Module::Decorator()->getTenantsManager()->getTenantById($iTenantId);
		if ($oTenant && $oTenant->{self::GetName() . '::IsBusiness'})
		{
			$iEmailAccountsLimit = $this->getBusinessTenantLimits($oTenant, 'EmailAccountsCount');
			if (is_int($iEmailAccountsLimit) && $iEmailAccountsLimit > 0)
			{
				$oEavManager = \Aurora\System\Managers\Eav::getInstance();
				$aFilters = ['IdTenant' => [$iTenantId, '=']];
				$iUserCount = $oEavManager->getEntitiesCount(\Aurora\Modules\Core\Models\User::class, $aFilters);
				if ($iUserCount >= $iEmailAccountsLimit)
				{
					throw new \Exception($this->i18N('ERROR_BUSINESS_TENANT_EMAIL_ACCOUNTS_LIMIT_PLURAL', ['COUNT' => $iEmailAccountsLimit], $iEmailAccountsLimit));
				}
			}
		}
		if (isset($aArgs['PublicId']) && $this->checkIfEmailReserved($aArgs['PublicId']))
		{
			$oUser = \Aurora\System\Api::getAuthenticatedUser();
			if (
				$oUser instanceof \Aurora\Modules\Core\Models\User
				&& ($oUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin
					|| $oUser->Role === \Aurora\System\Enums\UserRole::TenantAdmin)
				&& isset($aArgs['Forced']) && $aArgs['Forced'] === true
			)
			{
				//Only SuperAdmin or TenantAdmin can creaete User if it was reserved
			}
			else
			{
				throw new \Exception($this->i18N('ERROR_EMAIL_IS_RESERVED'));
			}
		}
	}

	public function onBeforeCreateAccount($aArgs, &$mResult)
	{
		if (isset($aArgs['Email']) && $this->checkIfEmailReserved($aArgs['Email']))
		{
			$oUser = \Aurora\System\Api::getAuthenticatedUser();
			if (
				$oUser instanceof \Aurora\Modules\Core\Models\User
				&& ($oUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin
					|| $oUser->Role === \Aurora\System\Enums\UserRole::TenantAdmin)
			)
			{
				//Only SuperAdmin or TenantAdmin can create Account if it was reserved
			}
			else
			{
				throw new \Exception($this->i18N('ERROR_EMAIL_IS_RESERVED'));
			}
		}
	}

	public function onBeforeAddNewAlias($aArgs, &$mResult)
	{
		if (
			isset($aArgs['AliasName']) && isset($aArgs['AliasDomain'])
			&& $this->checkIfEmailReserved($aArgs['AliasName'] . '@' . $aArgs['AliasDomain'])
		)
		{
			$oUser = \Aurora\System\Api::getAuthenticatedUser();
			if (
				$oUser instanceof \Aurora\Modules\Core\Models\User
				&& ($oUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin
					|| $oUser->Role === \Aurora\System\Enums\UserRole::TenantAdmin)
			)
			{
				//Only SuperAdmin or TenantAdmin can creaete alias if it was reserved
			}
			else
			{
				throw new \Exception($this->i18N('ERROR_EMAIL_IS_RESERVED'));
			}
		}
	}

	/**
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterCreateUser($aArgs, &$mResult)
	{
		if ($mResult)
		{
			$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserUnchecked($mResult);
			if ($oUser instanceof \Aurora\Modules\Core\Models\User)
			{
				$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantUnchecked($oUser->IdTenant);
				if ($oTenant instanceof \Aurora\Modules\Core\Models\Tenant)
				{
					if ($oTenant->{self::GetName() . '::IsBusiness'})
					{
						$UserSpaceLimitMb = $oTenant->{'Files::UserSpaceLimitMb'};

						\Aurora\Modules\Files\Module::Decorator()->CheckAllocatedSpaceLimitForUsersInTenant($oTenant, $UserSpaceLimitMb);

						$oUser->{'Files::UserSpaceLimitMb'} = $UserSpaceLimitMb;
						$oUser->saveAttribute('Files::UserSpaceLimitMb');
					}
					else
					{
						$oFilesDecorator = \Aurora\Modules\Files\Module::Decorator();
						$iFilesQuotaMb = $this->getGroupSetting($oUser->Id, 'FilesQuotaMb');
						if ($oFilesDecorator && is_int($iFilesQuotaMb))
						{
							$oFilesDecorator->UpdateUserSpaceLimit($oUser->Id, $iFilesQuotaMb);
						}
					}
				}
			}
		}
	}

	/**
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterCreateAccount($aArgs, &$mResult)
	{
		if ($mResult instanceof \Aurora\Modules\Mail\Models\MailAccount)
		{
			$oAccount = $mResult;
			$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserUnchecked($oAccount->IdUser);
			if ($oUser instanceof \Aurora\Modules\Core\Models\User)
			{
				$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantUnchecked($oUser->IdTenant);
				if ($oTenant instanceof \Aurora\Modules\Core\Models\Tenant
						&& $oUser->PublicId === $oAccount->Email)
				{
					$iMailQuotaMb = $oTenant->{self::GetName() . '::IsBusiness'}
						? $oTenant->{'Mail::UserSpaceLimitMb'}
						: $this->getGroupSetting($oUser->Id, 'MailQuotaMb');
					\Aurora\Modules\Mail\Module::Decorator()->UpdateEntitySpaceLimits('User', $oUser->Id, $oUser->IdTenant, null, $iMailQuotaMb);
				}
			}
		}
	}

	/**
	 * @deprecated since version 8.3.7
	 */
	public function onAfterAdminPanelCreateTenant($aArgs, &$mResult)
	{
		$this->onAfterCreateTenant($aArgs, $mResult);
	}

	public function onAfterCreateTenant($aArgs, &$mResult)
	{
		$iTenantId = $mResult;
		if (!empty($iTenantId))
		{
			$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantUnchecked($iTenantId);
			if ($oTenant && isset($aArgs[self::GetName() . '::IsBusiness']) && is_bool($aArgs[self::GetName() . '::IsBusiness']))
			{
				if (isset($aArgs[self::GetName() . '::IsBusiness']) && is_bool($aArgs[self::GetName() . '::IsBusiness']))
				{
					$aAttributesToSave = [];

					$oTenant->setExtendedProp(self::GetName() . '::IsBusiness', $aArgs[self::GetName() . '::IsBusiness']);
					$aAttributesToSave[] = self::GetName() . '::IsBusiness';

					if ($oTenant->{self::GetName() . '::IsBusiness'})
					{
						$oFilesModule = \Aurora\Api::GetModule('Files');
						$iFilesStorageQuotaMb = $this->getBusinessTenantLimitsFromConfig('FilesStorageQuotaMb');
						if ($oFilesModule)
						{
							$oTenant->{'Files::UserSpaceLimitMb'} = $oFilesModule->getConfig('UserSpaceLimitMb');
							$oTenant->{'Files::TenantSpaceLimitMb'} = $iFilesStorageQuotaMb;

							$aAttributesToSave[] = 'Files::UserSpaceLimitMb';
							$aAttributesToSave[] = 'Files::TenantSpaceLimitMb';
						}

						$iMailStorageQuotaMb = $this->getBusinessTenantLimitsFromConfig('MailStorageQuotaMb');
						if (is_int($iMailStorageQuotaMb))
						{
							$oTenant->{'Mail::TenantSpaceLimitMb'} = $iMailStorageQuotaMb;
							$aAttributesToSave[] = 'Mail::TenantSpaceLimitMb';
						}
					}
					else
					{
						$oTenant->{'Mail::AllowChangeUserSpaceLimit'} = false;
						$aAttributesToSave[] = 'Mail::AllowChangeUserSpaceLimit';
					}

					$oTenant->saveAttributes($aAttributesToSave);
				}

				if (isset($aArgs[self::GetName() . '::EnableGroupware']) && is_bool($aArgs[self::GetName() . '::EnableGroupware']))
				{
					$this->UpdateGroupwareState($iTenantId, $aArgs[self::GetName() . '::EnableGroupware']);
				}
			}
		}
	}

	/**
	 * @deprecated since version 8.3.7
	 */
	public function onAfterAdminPanelUpdateTenant($aArgs, &$mResult)
	{
		$this->onAfterUpdateTenant($aArgs, $mResult);
	}

	public function onAfterUpdateTenant($aArgs, &$mResult)
	{
		$oUser = \Aurora\Api::getAuthenticatedUser();
		if ($oUser instanceof  \Aurora\Modules\Core\Models\User && $oUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin)
		{
			$iTenantId = (int) $aArgs['TenantId'];
			if (!empty($iTenantId))
			{
				$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantUnchecked($iTenantId);
				if ($oTenant)
				{
					if (isset($aArgs[self::GetName() . '::EnableGroupware']) && is_bool($aArgs[self::GetName() . '::EnableGroupware']))
					{
						$this->UpdateGroupwareState($iTenantId, $aArgs[self::GetName() . '::EnableGroupware']);
					}
				}
			}
		}
	}

	/**
	 * Update all extended props of the group.
	 * @param array $aArgs
	 * @param boolean $mResult
	 */
	public function onAfterUpdateGroup($aArgs, &$mResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		$oCoreUserGroupsDecorator = \Aurora\Modules\CoreUserGroups\Module::Decorator();
		if ($mResult && $oCoreUserGroupsDecorator)
		{
			$oGroup = $oCoreUserGroupsDecorator->GetGroup($aArgs['Id']);
			if ($oGroup instanceof \Aurora\Modules\CoreUserGroups\Models\Group)
			{
				$oGroup->setExtendedProp(self::GetName() . '::DataSavedInDb', true);
				$oGroup->setExtendedProp(self::GetName() . '::EmailSendLimitPerDay', $aArgs[self::GetName() . '::EmailSendLimitPerDay']);
				$oGroup->setExtendedProp(self::GetName() . '::MailSignature', $aArgs[self::GetName() . '::MailSignature']);
				$oGroup->setExtendedProp(self::GetName() . '::MailQuotaMb', $aArgs[self::GetName() . '::MailQuotaMb']);
				$oGroup->setExtendedProp(self::GetName() . '::FilesQuotaMb', $aArgs[self::GetName() . '::FilesQuotaMb']);
				$oGroup->setExtendedProp(self::GetName() . '::AllowMobileApps', $aArgs[self::GetName() . '::AllowMobileApps']);
				$oGroup->setExtendedProp(self::GetName() . '::BannerUrlMobile', $aArgs[self::GetName() . '::BannerUrlMobile']);
				$oGroup->setExtendedProp(self::GetName() . '::BannerUrlDesktop', $aArgs[self::GetName() . '::BannerUrlDesktop']);
				$oGroup->setExtendedProp(self::GetName() . '::BannerLink', $aArgs[self::GetName() . '::BannerLink']);
				$oGroup->setExtendedProp(self::GetName() . '::MaxAllowedActiveAliasCount', $aArgs[self::GetName() . '::MaxAllowedActiveAliasCount']);
				$oGroup->setExtendedProp(self::GetName() . '::AliasCreationIntervalDays', $aArgs[self::GetName() . '::AliasCreationIntervalDays']);
				$mResult = $mResult && $oGroup->save();

				$aGroupUsers = \Aurora\Modules\CoreUserGroups\Module::Decorator()->GetGroupUsers($oGroup->Id, $oGroup->TenantId);
				$aGroupUsersIds = array_map(function ($oUser) {
					return $oUser['Id'];
				}, $aGroupUsers);
				$this->setUserListCapabilities($aGroupUsersIds);
			}
		}
	}

	public function onAfterGetSettingsForEntity($aArgs, &$mResult)
	{
		if (isset($aArgs['EntityType'], $aArgs['EntityId']) && 	$aArgs['EntityType'] === 'Tenant')
		{
			$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantUnchecked($aArgs['EntityId']);
			if ($oTenant instanceof \Aurora\Modules\Core\Models\Tenant)
			{
				$mResult['AllowEditUserSpaceLimitMb'] = $oTenant->{self::GetName() . '::IsBusiness'};
			}
		}
		if (isset($aArgs['EntityType'], $aArgs['EntityId']) && 	$aArgs['EntityType'] === 'User')
		{
			$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserUnchecked($aArgs['EntityId']);
			if ($oUser instanceof \Aurora\Modules\Core\Models\User)
			{
				$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantUnchecked($oUser->IdTenant);
				if ($oTenant instanceof \Aurora\Modules\Core\Models\Tenant)
				{
					$mResult['AllowEditUserSpaceLimitMb'] = $oTenant->{self::GetName() . '::IsBusiness'};
				}
			}
		}
	}

	public function onAfterIsEmailAllowedForCreation($aArgs, &$mResult)
	{
		if ($mResult && isset($aArgs['Email']))
		{
			$mResult = !$this->checkIfEmailReserved($aArgs['Email']);
		}
	}

	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$aSettings = array(
			'BannerUrlMobile' => '',
			'BannerUrlDesktop' => '',
			'BannerLink' => '',
		);

		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();

		if ($this->isUserNotFromBusinessTenant($oAuthenticatedUser))
		{
			$aSettings['BannerUrlMobile'] = $this->getGroupSetting($oAuthenticatedUser->Id, 'BannerUrlMobile');
			$aSettings['BannerUrlDesktop'] = $this->getGroupSetting($oAuthenticatedUser->Id, 'BannerUrlDesktop');
			$aSettings['BannerLink'] = $this->getGroupSetting($oAuthenticatedUser->Id, 'BannerLink');
		}

		return $aSettings;
	}

	public function UpdateBusinessTenantLimits($TenantId, $AliasesCount, $EmailAccountsCount, $MailStorageQuotaMb, $FilesStorageQuotaMb)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);

		$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantUnchecked($TenantId);
		if ($oTenant instanceof \Aurora\Modules\Core\Models\Tenant && $oTenant->{self::GetName() . '::IsBusiness'})
		{
			$aAttributesToSave = [];
			if (is_int($AliasesCount))
			{
				$oTenant->setExtendedProp(self::GetName() . '::AliasesCount', $AliasesCount);
				$aAttributesToSave[] = self::GetName() . '::AliasesCount';
			}
			if (is_int($EmailAccountsCount))
			{
				$oTenant->setExtendedProp(self::GetName() . '::EmailAccountsCount', $EmailAccountsCount);
				$aAttributesToSave[] = self::GetName() . '::EmailAccountsCount';
			}
			if (!empty($aAttributesToSave))
			{
				$oTenant->save();
			}
			if (is_int($MailStorageQuotaMb))
			{
				\Aurora\Modules\Mail\Module::Decorator()->UpdateEntitySpaceLimits('Tenant', 0, $TenantId, $MailStorageQuotaMb);
			}
			if (is_int($FilesStorageQuotaMb))
			{
				\Aurora\Modules\Files\Module::Decorator()->UpdateSettingsForEntity('Tenant', $TenantId, null, $FilesStorageQuotaMb);
			}
			return true;
		}

		return false;
	}

	/**
	 * Reterns a list of reserved names
	 */
	public function GetReservedNames()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);

		return $this->getConfig('ReservedList', []);
	}

	/**
	 * Adds a new item to the reserved list
	 *
	 * @param string AccountName
	 * @return boolean
	 */
	public function AddNewReservedName($AccountName)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);

		$bResult = false;
		$sAccountName = strtolower($AccountName);
		$aCurrentReservedList = $this->getConfig('ReservedList', []);
		if (in_array($sAccountName, $aCurrentReservedList))
		{
			throw new \Exception($this->i18N('ERROR_NAME_ALREADY_IN_RESERVED_LIST'));
		}
		else
		{
			try
			{
				$aCurrentReservedList[] = $sAccountName;
				$this->setConfig('ReservedList', $aCurrentReservedList);
				$this->saveModuleConfig();
				$bResult = true;
			}
			catch (\Exception $ex)
			{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CanNotSaveSettings);
			}
		}

		return $bResult;
	}

	/**
	 * Removes the specified names from the list
	 *
	 * @param array ReservedNames
	 * @return boolean
	 */
	public function DeleteReservedNames($ReservedNames)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);

		$bResult = false;

		if (!is_array($ReservedNames) || empty($ReservedNames))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}
		else
		{
			$aCurrentReservedList = $this->getConfig('ReservedList', []);
			$newReservedList = array_diff($aCurrentReservedList, $ReservedNames);
			//"array_values" needed to reset array keys after deletion
			$this->setConfig('ReservedList', array_values($newReservedList));
			$this->saveModuleConfig();
			$bResult = true;
		}

		return $bResult;
	}
}
