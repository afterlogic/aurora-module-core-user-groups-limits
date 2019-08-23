<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\CoreUserGroupsLimits;

/**
 * Provides user groups.
 * 
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
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
		
		$this->subscribeEvent('CoreUserGroups::DeleteGroups::after', array($this, 'onAfterRemoveDeleteGroups'));
		$this->subscribeEvent('CoreUserGroups::RemoveUsersFromGroup::after', array($this, 'onAfterRemoveUsersFromGroup'));
		$this->subscribeEvent('CoreUserGroups::AddToGroup::after', array($this, 'onAfterAddToGroup'));
		$this->subscribeEvent('CoreUserGroups::UpdateUserGroup::after', array($this, 'onAfterSaveGroupsOfUser'));
		$this->subscribeEvent('CoreUserGroups::GetGroups::before', array($this, 'onBeforeGetGroups'));
		$this->subscribeEvent('CoreUserGroups::CreateGroup::before', array($this, 'onBeforeCreateGroup'));
		
		$this->subscribeEvent('CpanelIntegrator::CreateAlias::before', array($this, 'onBeforeCreateAlias'));
		$this->subscribeEvent('CpanelIntegrator::GetSettings::after', array($this, 'onAfterGetSettings'));
		
		$this->subscribeEvent('PersonalFiles::GetUserSpaceLimitMb', array($this, 'onGetUserSpaceLimitMb'));

		$this->subscribeEvent('System::RunEntry::before', array($this, 'onBeforeRunEntry'));
		$this->subscribeEvent('Files::GetSettingsForEntity::after', array($this, 'onAfterGetSettingsForEntity'));
		
		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oAuthenticatedUser instanceof \Aurora\Modules\Core\Classes\User)
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
		
		\Aurora\Modules\Core\Classes\Tenant::extend(
			self::GetName(),
			[
				'IsBusiness' => array('bool', false),
			]
		);
		
		\Aurora\Modules\Core\Classes\User::extend(
			self::GetName(),
			[
				'EmailSentCount' => array('int', 0),
				'EmailSentDate' => array('datetime', date('Y-m-d'), true),
			]
		);
	}
	
	private function getGroupName($iUserId)
	{
		$sGroupName = 'Free';
		
		$oCoreUserGroupsDecorator = \Aurora\Modules\CoreUserGroups\Module::Decorator();
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserUnchecked($iUserId);
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$oGroup = $oCoreUserGroupsDecorator->GetGroup($oUser->{'CoreUserGroups::GroupId'});
			if ($oGroup instanceof \Aurora\Modules\CoreUserGroups\Classes\Group)
			{
				$sGroupName = $oGroup->Name;
			}
		}
		
		return $sGroupName;
	}
	
	protected function getGroupSetting($iUserId, $sSettingName)
	{
		$sGroupName = $this->getGroupName($iUserId);
		$aGroupsLimits = $this->getConfig('GroupsLimits', '');
		$mSettingValue = null;
		$iIndex = array_search($sGroupName, array_column($aGroupsLimits, 'GroupName'));
		if ($iIndex === false)
		{
			$iIndex = 0;
		}
		if (isset($aGroupsLimits[$iIndex]) && isset($aGroupsLimits[$iIndex][$sSettingName]))
		{
			$mSettingValue = $aGroupsLimits[$iIndex][$sSettingName];
		}
		return $mSettingValue;
	}
	
	protected function isTodayEmailSentDate($oUser)
	{
		return $oUser instanceof \Aurora\Modules\Core\Classes\User
				&& ($oUser->{self::GetName() . '::EmailSentDate'} === date('Y-m-d')
				|| $oUser->{self::GetName() . '::EmailSentDate'} === date('Y-m-d') . ' 00:00:00');
	}
	
	protected function isUserNotFromBusinessTenant($oUser)
	{
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantUnchecked($oUser->IdTenant);
			if ($oTenant instanceof \Aurora\Modules\Core\Classes\Tenant && !$oTenant->{self::GetName() . '::IsBusiness'})
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
				$iEmailSendLimitPerDay = $this->getGroupSetting($oAuthenticatedUser->EntityId, 'EmailSendLimitPerDay');

				if ($oAuthenticatedUser->{self::GetName() . '::EmailSentCount'} >= $iEmailSendLimitPerDay)
				{
					throw new \Exception($this->i18N('ERROR_USER_SENT_MESSAGES_LIMIT', ['COUNT' => $iEmailSendLimitPerDay]));
				}
			}

			$sMailSignature = $this->getGroupSetting($oAuthenticatedUser->EntityId, 'MailSignature');
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
				$oAuthenticatedUser->{self::GetName() . '::EmailSentCount'} += 1;
			}
			else
			{
				$oAuthenticatedUser->{self::GetName() . '::EmailSentCount'} = 1;
			}
			$oAuthenticatedUser->{self::GetName() . '::EmailSentDate'} = date('Y-m-d');
			$oAuthenticatedUser->saveAttributes([self::GetName() . '::EmailSentCount', self::GetName() . '::EmailSentDate']);
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
	 * Applies capabilities for users from deleted groups.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterRemoveDeleteGroups(&$aArgs, &$mResult)
	{
		if (is_array($mResult) && !empty($mResult))
		{
			$this->setUserListCapabilities($mResult);
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
	
	/**
	 * Applies capabilities for user.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterSaveGroupsOfUser($aArgs, &$mResult)
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
	}
	
	public function onBeforeCreateAlias($aArgs, &$mResult)
	{
		$iTenantId = $aArgs['TenantId'];
		$oTenant = \Aurora\Modules\Core\Module::Decorator()->getTenantsManager()->getTenantById($iTenantId);
		if ($oTenant && $oTenant->{self::GetName() . '::IsBusiness'})
		{
			$iAliasesCount = $this->getBusinessTenantLimits('AliasesCount');
			if (is_array($aArgs['Forwarders']) && count($aArgs['Forwarders']) >= $iAliasesCount)
			{
				throw new \Exception($this->i18N('ERROR_BUSINESS_TENANT_ALIASES_LIMIT'));
			}
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
	
	/**
	 * Applies capabilities for users.
	 * @param array $aUsersIds
	 */
	protected function setUserListCapabilities($aUsersIds)
	{
		$oEavManager = \Aurora\System\Managers\Eav::getInstance();
		$aFilters = ['EntityId' => [$aUsersIds, 'IN']];
		$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
		$aUsers = [];
		if ($oCoreDecorator && !empty($aUsersIds))
		{
			$aUsers = $oEavManager->getEntities(\Aurora\Modules\Core\Classes\User::class, [], 0, 0, $aFilters, 'PublicId', \Aurora\System\Enums\SortOrder::ASC);
		}
		
		foreach ($aUsers as $oUser)
		{
			$this->setUserCapabilities($oUser);
		}
	}
	
	/**
	 * Applies capabilities for user.
	 * @param \Aurora\Modules\Core\Classes\User $oUser
	 */
	protected function setUserCapabilities($oUser)
	{
		if ($this->isUserNotFromBusinessTenant($oUser))
		{
			$iMailQuotaMb = (int) $this->getGroupSetting($oUser->EntityId, 'MailQuotaMb');
			\Aurora\Modules\Mail\Module::Decorator()->UpdateEntitySpaceLimits('User', $oUser->EntityId, $oUser->IdTenant, null, $iMailQuotaMb);

			$oFilesDecorator = \Aurora\Modules\Files\Module::Decorator();
			$iFilesQuotaMb = $this->getGroupSetting($oUser->EntityId, 'FilesQuotaMb');
			if ($oFilesDecorator && is_int($iFilesQuotaMb))
			{
				$oFilesDecorator->UpdateUserSpaceLimit($oUser->EntityId, $iFilesQuotaMb);
			}
		}
	}

	public function GetGroupwareState($TenantId)
	{
		$bState = false;
		$oAuthenticatedUser = \Aurora\Api::getAuthenticatedUser();
		if ($oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin || ($oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::TenantAdmin && $oAuthenticatedUser->IdTenant === $TenantId))
		{
			$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantUnchecked($TenantId);
			if ($oTenant instanceof \Aurora\Modules\Core\Classes\Tenant)
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
			if ($oTenant instanceof \Aurora\Modules\Core\Classes\Tenant)
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
		if ($oAuthenticatedUser instanceof \Aurora\Modules\Core\Classes\User
				&& $oAuthenticatedUser->isNormalOrTenant()
				&& $this->isUserNotFromBusinessTenant($oAuthenticatedUser) && isset($aArgs['EntryName']) && strtolower($aArgs['EntryName']) === 'api')
		{
			$sXClientHeader = \MailSo\Base\Http::SingletonInstance()->GetHeader('X-Client');
			$bAllowMobileApps = $this->getGroupSetting($oAuthenticatedUser->EntityId, 'AllowMobileApps');
			if ($sXClientHeader && strtolower($sXClientHeader) !== 'webclient' && (!is_bool($bAllowMobileApps) || !$bAllowMobileApps))
			{
				$mResult = \Aurora\System\Managers\Response::GetJsonFromObject(
					'Json', 
					\Aurora\System\Managers\Response::ExceptionResponse(
						'RunEntry', 
						new \Aurora\System\Exceptions\ApiException(
							\Aurora\System\Notifications::AccessDenied,
							null, 
							'', 
							[], 
							$this
						)	
					)
				);

				return true;
			}
		}
	}
	
	public function onTenantToResponseArray($aArgs, &$mResult)
	{
		$oTenant = $aArgs['Tenant'];
		if ($oTenant instanceof \Aurora\Modules\Core\Classes\Tenant && is_array($mResult))
		{
			$mResult[self::GetName() . '::IsBusiness'] = $oTenant->{self::GetName() . '::IsBusiness'};
			$mResult[self::GetName() . '::EnableGroupware'] = $this->GetGroupwareState($oTenant->EntityId);
		}
	}
	
	protected function getBusinessTenantLimits($sSettingName)
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
			$iEmailAccountsLimit = $this->getBusinessTenantLimits('EmailAccountsCount');
			if (is_int($iEmailAccountsLimit) && $iEmailAccountsLimit > 0)
			{
				$oEavManager = \Aurora\System\Managers\Eav::getInstance();
				$aFilters = ['IdTenant' => [$iTenantId, '=']];
				$iUserCount = $oEavManager->getEntitiesCount(\Aurora\Modules\Core\Classes\User::class, $aFilters);
				if ($iUserCount >= $iEmailAccountsLimit)
				{
					throw new \Exception($this->i18N('ERROR_BUSINESS_TENANT_EMAIL_ACCOUNTS_LIMIT'));
				}
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
			if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
			{
				$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantUnchecked($oUser->IdTenant);
				if ($oTenant instanceof \Aurora\Modules\Core\Classes\Tenant)
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
						$iFilesQuotaMb = $this->getGroupSetting($oUser->EntityId, 'FilesQuotaMb');
						if ($oFilesDecorator && is_int($iFilesQuotaMb))
						{
							$oFilesDecorator->UpdateUserSpaceLimit($oUser->EntityId, $iFilesQuotaMb);
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
		if ($mResult instanceof \Aurora\Modules\Mail\Classes\Account)
		{
			$oAccount = $mResult;
			$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserUnchecked($oAccount->IdUser);
			if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
			{
				$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantUnchecked($oUser->IdTenant);
				if ($oTenant instanceof \Aurora\Modules\Core\Classes\Tenant
						&& $oUser->PublicId === $oAccount->Email)
				{
					$iMailQuotaMb = $oTenant->{self::GetName() . '::IsBusiness'}
						? $oTenant->{'Mail::UserSpaceLimitMb'}
						: $this->getGroupSetting($oUser->EntityId, 'MailQuotaMb');
					\Aurora\Modules\Mail\Module::Decorator()->UpdateEntitySpaceLimits('User', $oUser->EntityId, $oUser->IdTenant, null, $iMailQuotaMb);
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

					$oTenant->{self::GetName() . '::IsBusiness'} = $aArgs[self::GetName() . '::IsBusiness'];
					$aAttributesToSave[] = self::GetName() . '::IsBusiness';

					if ($oTenant->{self::GetName() . '::IsBusiness'})
					{
						$oFilesModule = \Aurora\Api::GetModule('Files');
						$iFilesStorageQuotaMb = $this->getBusinessTenantLimits('FilesStorageQuotaMb');
						if ($oFilesModule)
						{
							$oTenant->{'Files::UserSpaceLimitMb'} = $oFilesModule->getConfig('UserSpaceLimitMb');
							$oTenant->{'Files::TenantSpaceLimitMb'} = $iFilesStorageQuotaMb;
			
							$aAttributesToSave[] = 'Files::UserSpaceLimitMb';
							$aAttributesToSave[] = 'Files::TenantSpaceLimitMb';
						}
						
						$iMailStorageQuotaMb = $this->getBusinessTenantLimits('MailStorageQuotaMb');
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
		if ($oUser instanceof  \Aurora\Modules\Core\Classes\User && $oUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin)
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


	public function onAfterGetSettingsForEntity($aArgs, &$mResult)
	{
		if (isset($aArgs['EntityType'], $aArgs['EntityId']) && 	$aArgs['EntityType'] === 'Tenant')
		{
			$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantUnchecked($aArgs['EntityId']);
			if ($oTenant instanceof \Aurora\Modules\Core\Classes\Tenant)
			{
				$mResult['AllowEditUserSpaceLimitMb'] = $oTenant->{self::GetName() . '::IsBusiness'};
			}
		}
		if (isset($aArgs['EntityType'], $aArgs['EntityId']) && 	$aArgs['EntityType'] === 'User')
		{
			$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserUnchecked($aArgs['EntityId']);
			if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
			{
				$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantUnchecked($oUser->IdTenant);
				if ($oTenant instanceof \Aurora\Modules\Core\Classes\Tenant)
				{
					$mResult['AllowEditUserSpaceLimitMb'] = $oTenant->{self::GetName() . '::IsBusiness'};
				}
			}
		}
	}
}
