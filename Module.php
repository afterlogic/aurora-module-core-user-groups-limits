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
		
		$this->subscribeEvent('Core::Login::after', array($this, 'onAfterLogin'));
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
		if ($oAuthenticatedUser instanceof \Aurora\Modules\Core\Classes\User && $oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin)
		{
			$this->aAdditionalEntityFieldsToEdit = [
				[
					'DisplayName' => $this->i18N('LABEL_ITS_BUSINESS_TENANT'),
					'Entity' => 'Tenant',
					'FieldName' => self::GetName() . '::IsBusiness',
					'FieldType' => 'bool',
					'Hint' => $this->i18N('HINT_ITS_BUSINESS_TENANT_HTML'),
					'EnableOnCreate' => true,
					'EnableOnEdit' => false,
				],
			];
		}

		$this->subscribeEvent('Core::CreateUser::before', array($this, 'onBeforeCreateUser'));
		$this->subscribeEvent('AdminPanelWebclient::CreateTenant::after', array($this, 'onAfterCreateTenant'));
		$this->subscribeEvent('Core::Tenant::ToResponseArray', array($this, 'onTenantToResponseArray'));
		$this->subscribeEvent('Core::CreateUser::after', array($this, 'onAfterCreateUser'));
		
		\Aurora\Modules\Core\Classes\Tenant::extend(
			self::GetName(),
			[
				'IsBusiness' => array('bool', false),
			]
		);		
	}
	
	private function getGroupName($iUserId)
	{
		$sGroupName = 'Free';
		
		$oCoreUserGroupsDecorator = \Aurora\Modules\CoreUserGroups\Module::Decorator();
		$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
		$oUser = $oCoreDecorator ? $oCoreDecorator->GetUser($iUserId) : null;
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
	
	/**
	 * @param array $aArguments
	 * @param mixed $mResult
	 */
	public function onBeforeSendMessage(&$aArguments, &$mResult)
	{
		$iAuthenticatedUserId = \Aurora\System\Api::getAuthenticatedUserId();
		$sMailSignature = $this->getGroupSetting($iAuthenticatedUserId, 'MailSignature');
		if (is_string($sMailSignature) && $sMailSignature !== '')
		{
			$aArguments['Text'] .= ($aArguments['IsHtml'] ? '<br />' : "\r\n") . $sMailSignature;
		}
	}
	
	public function onGetUserSpaceLimitMb(&$aArgs, &$mResult)
	{
		$iFilesQuotaMb = $this->getGroupSetting($aArgs['UserId'], 'FilesQuotaMb');
		if (is_int($iFilesQuotaMb))
		{
			$mResult = $iFilesQuotaMb;
		}
	}
	
	/**
	 * Applies capabilities for just authenticated user.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterLogin(&$aArgs, &$mResult)
	{
		if ($mResult !== false)
		{
			$oUser = \Aurora\System\Api::getAuthenticatedUser();
			if ($oUser instanceof \Aurora\Modules\Core\Classes\User && $oUser->isNormalOrTenant())
			{
				// DB operations are not allowed for super admin here (DB might not be configured yet)
				$this->setUserCapabilities($oUser);
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
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$oTenant = \Aurora\Modules\Core\Module::Decorator()->getTenantsManager()->getTenantById($oUser->IdTenant);
			if ($oTenant && !$oTenant->{self::GetName() . '::IsBusiness'})
			{
				$oMailDecorator = \Aurora\Modules\Mail\Module::Decorator();
				$iMailQuotaMb = $this->getGroupSetting($oUser->EntityId, 'MailQuotaMb');
				if ($oMailDecorator && is_int($iMailQuotaMb))
				{
					$oMailDecorator->UpdateEntitySpaceLimits('User', $oUser->EntityId, $oUser->IdTenant, $iMailQuotaMb);
				}

				$oFilesDecorator = \Aurora\Modules\Files\Module::Decorator();
				$iFilesQuotaMb = $this->getGroupSetting($oUser->EntityId, 'FilesQuotaMb');
				if ($oFilesDecorator && is_int($iFilesQuotaMb))
				{
					$oFilesDecorator->UpdateUserSpaceLimit($oUser->EntityId, $iFilesQuotaMb);
				}
			}
		}
	}

	public function onBeforeRunEntry(&$aArgs, &$mResult)
	{
		$iUserId = \Aurora\Api::getAuthenticatedUserId();
		if ($iUserId > 0 && isset($aArgs['EntryName']) && strtolower($aArgs['EntryName']) === 'api')
		{
			$sXClientHeader = \MailSo\Base\Http::SingletonInstance()->GetHeader('X-Client');
			$bAllowMobileApps = $this->getGroupSetting($iUserId, 'AllowMobileApps');
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
	 * 
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterCreateUser($aArgs, &$mResult)
	{
		if ($mResult)
		{
			$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUser($mResult);
			if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
			{
				$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantById($oUser->IdTenant);
				if ($oTenant instanceof \Aurora\Modules\Core\Classes\Tenant)
				{
					$UserSpaceLimitMb = $oTenant->{'Files::UserSpaceLimitMb'};

					\Aurora\Modules\Files\Module::Decorator()->CheckAllocatedSpaceLimitForUsersInTenant($oTenant, $UserSpaceLimitMb);

					$oUser->{'Files::UserSpaceLimitMb'} = $UserSpaceLimitMb;
					$oUser->saveAttribute('Files::UserSpaceLimitMb');
				}
			}
		}
	}


	public function onAfterCreateTenant($aArgs, &$mResult)
	{
		$iTenantId = $mResult;
		if (!empty($iTenantId))
		{
			$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantById($iTenantId);
			if ($oTenant && isset($aArgs[self::GetName() . '::IsBusiness']) && is_bool($aArgs[self::GetName() . '::IsBusiness']))
			{
				$aAttributesToSave = [];

				$oTenant->{self::GetName() . '::IsBusiness'} = $aArgs[self::GetName() . '::IsBusiness'];
				$aAttributesToSave[] = self::GetName() . '::IsBusiness';

				if ($oTenant->{self::GetName() . '::IsBusiness'})
				{
					$iMailStorageQuotaMb = $this->getBusinessTenantLimits('MailStorageQuotaMb');
					if (is_int($iMailStorageQuotaMb))
					{
						$oTenant->{'Mail::TenantSpaceLimitMb'} = $iMailStorageQuotaMb;
						$aAttributesToSave[] = 'Mail::TenantSpaceLimitMb';
					}
					
					$oFilesModule = \Aurora\Api::GetModule('Files');
					if ($oFilesModule)
					{
						$oTenant->{'Files::UserSpaceLimitMb'} = $oFilesModule->getConfig('UserSpaceLimitMb');
						$oTenant->{'Files::TenantSpaceLimitMb'} = $oFilesModule->getConfig('TenantSpaceLimitMb');

						$aAttributesToSave[] = 'Files::UserSpaceLimitMb';
						$aAttributesToSave[] = 'Files::TenantSpaceLimitMb';
					}
				}
				else
				{
					$oTenant->{'Mail::AllowChangeUserSpaceLimit'} = false;
					$aAttributesToSave[] = 'Mail::AllowChangeUserSpaceLimit';
				}

				$oTenant->saveAttributes($aAttributesToSave);
			}
		}
	}

	public function onAfterGetSettingsForEntity($aArgs, &$mResult)
	{
		if (isset($aArgs['EntityType'], $aArgs['EntityId']) && 	$aArgs['EntityType'] === 'Tenant')
		{
			$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantById($aArgs['EntityId']);
			if ($oTenant instanceof \Aurora\Modules\Core\Classes\Tenant)
			{
				$mResult['AllowEditUserSpaceLimitMb'] = $oTenant->{self::GetName() . '::IsBusiness'};
			}
		}
	}
}
