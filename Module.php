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
		$this->subscribeEvent('CoreUserGroups::SaveGroupsOfUser::after', array($this, 'onAfterSaveGroupsOfUser'));
		
		$this->subscribeEvent('PersonalFiles::GetUserSpaceLimitMb', array($this, 'onGetUserSpaceLimitMb'));

		$this->subscribeEvent('System::RunEntry::before', array($this, 'onBeforeRunEntry'));
		
		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oAuthenticatedUser instanceof \Aurora\Modules\Core\Classes\User && $oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin)
		{
			$this->aAdditionalEntityFieldsToEdit = [
				[
					'DisplayName' => $this->i18N('LABEL_ITS_BUSINESS_TENANT'),
					'Entity' => 'Tenant',
					'FieldName' => self::GetName() . '::IsBusiness',
					'FieldType' => 'bool',
					'Hint' => $this->i18N('HINT_ITS_BUSINESS_TENANT')
				],
			];
		}

		$this->subscribeEvent('AdminPanelWebclient::UpdateEntity::after', array($this, 'onAfterUpdateEntity'));
		$this->subscribeEvent('AdminPanelWebclient::CreateTenant::after', array($this, 'onAfterCreateTenant'));
		$this->subscribeEvent('Core::Tenant::ToResponseArray', array($this, 'onTenantToResponseArray'));
		
		\Aurora\Modules\Core\Classes\Tenant::extend(
			self::GetName(),
			[
				'IsBusiness' => array('bool', false),
			]
		);		
	}
	
	private function getGroupName($iUserId)
	{
		$oCoreUserGroupsDecorator = \Aurora\Modules\CoreUserGroups\Module::Decorator();
		$aUserGroups = $oCoreUserGroupsDecorator->GetGroupNamesOfUser($iUserId);
		$sGroupName = 'Free';
		if (in_array('Pro', $aUserGroups))
		{
			$sGroupName = 'Pro';
		}
		else if (in_array('Standard', $aUserGroups))
		{
			$sGroupName = 'Standard';
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
	public function onAfterSaveGroupsOfUser(&$aArgs, &$mResult)
	{
		if ($mResult)
		{
			$this->setUserListCapabilities([$aArgs['UserId']]);
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
			$oCpanelIntegratorDecorator = \Aurora\Modules\CpanelIntegrator\Module::Decorator();
			$iMailQuotaMb = $this->getGroupSetting($oUser->EntityId, 'MailQuotaMb');
			if ($oCpanelIntegratorDecorator && is_int($iMailQuotaMb))
			{
				try
				{
					$oCpanelIntegratorDecorator->SetMailQuota($oUser->PublicId, $iMailQuota);
				}
				catch (\Exception $oException)
				{
					\Aurora\System\Api::LogException($oException);
				}
			}
		}
	}

	public function onBeforeRunEntry(&$aArgs, &$mResult)
	{
		$iUserId = \Aurora\Api::getAuthenticatedUserId();
		if (isset($aArgs['EntryName']) && strtolower($aArgs['EntryName']) === 'api')
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
	
	public function onAfterUpdateEntity(&$aArgs, &$mResult)
	{
		if ($aArgs['Type'] === 'Tenant' && is_array($aArgs['Data']))
		{
			$iTenantId = $aArgs['Data']['Id'];
			
			if (!empty($iTenantId))
			{
				$oTenant = \Aurora\Modules\Core\Module::Decorator()->getTenantsManager()->getTenantById($iTenantId);
				if ($oTenant)
				{
					if (isset($aArgs['Data'][self::GetName() . '::IsBusiness']) && is_bool($aArgs['Data'][self::GetName() . '::IsBusiness']))
					{
						$oTenant->{self::GetName() . '::IsBusiness'} = $aArgs['Data'][self::GetName() . '::IsBusiness'];
					}
					return \Aurora\Modules\Core\Module::Decorator()->getTenantsManager()->updateTenant($oTenant);
				}
			}
		}
	}
	
	public function onAfterCreateTenant(&$aArgs, &$mResult)
	{
		$iTenantId = $mResult;
		if (!empty($iTenantId))
		{
			$oTenant = \Aurora\Modules\Core\Module::Decorator()->getTenantsManager()->getTenantById($iTenantId);
			if ($oTenant)
			{
				if (isset($aArgs[self::GetName() . '::IsBusiness']) && is_bool($aArgs[self::GetName() . '::IsBusiness']))
				{
					$oTenant->{self::GetName() . '::IsBusiness'} = $aArgs[self::GetName() . '::IsBusiness'];
				}
				return \Aurora\Modules\Core\Module::Decorator()->getTenantsManager()->updateTenant($oTenant);
			}
		}
	}
}
